<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\EventSubscriber;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tourze\AnthropicTokenUsageBundle\Interface\UsageCollectorInterface;
use Tourze\AnthropicTokenUsageBundle\ValueObject\AnthropicUsageData;
use Tourze\HttpForwardBundle\Event\AfterForwardEvent;
use Tourze\HttpForwardBundle\Event\ForwardEvents;

/**
 * 监听HTTP转发事件，自动收集Anthropic API的token使用情况
 */
#[WithMonologChannel(channel: 'anthropic_token_usage')]
final readonly class HttpForwardEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private UsageCollectorInterface $usageCollector,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ForwardEvents::AFTER_FORWARD => 'onAfterForward',
        ];
    }

    public function onAfterForward(AfterForwardEvent $event): void
    {
        $response = $event->getResponse();
        $request = $event->getRequest();

        // 只处理成功的响应
        if (!$this->isSuccessfulResponse($response)) {
            return;
        }

        // 检查是否是Anthropic API调用
        if (!$this->isAnthropicApiCall($request->getPathInfo())) {
            return;
        }

        try {
            $this->processAnthropicResponse($event);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to process Anthropic response for token usage', [
                'error' => $e->getMessage(),
                'path' => $request->getPathInfo(),
                'rule_id' => $event->getRule()->getId(),
                'exception' => $e,
            ]);
        }
    }

    private function isSuccessfulResponse(Response $response): bool
    {
        return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
    }

    private function isAnthropicApiCall(string $path): bool
    {
        // 检查路径是否包含常见的Anthropic API端点模式
        $anthropicPatterns = [
            '/messages',
            '/claude',
            '/anthropic',
            '/api/v1/messages',
        ];

        foreach ($anthropicPatterns as $pattern) {
            if (str_contains($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function processAnthropicResponse(AfterForwardEvent $event): void
    {
        $response = $event->getResponse();
        $request = $event->getRequest();
        $responseContent = $response->getContent();

        if (false === $responseContent || '' === $responseContent) {
            $this->logger->debug('Empty response content, skipping token usage collection');

            return;
        }

        // 处理流式响应
        $usageData = $this->extractUsageFromResponse($responseContent);
        if (null === $usageData || $usageData->isEmpty()) {
            $this->logger->debug('No usage data found in response', [
                'path' => $request->getPathInfo(),
                'response_length' => strlen($responseContent),
            ]);

            return;
        }

        // 从ForwardLog获取AccessKey信息
        $forwardLog = $event->getForwardLog();
        $accessKey = $forwardLog?->getAccessKey();
        $user = $accessKey?->getOwner();

        // 构建元数据
        $metadata = $this->buildMetadata($request, $event, $responseContent);

        // 收集使用情况
        $success = $this->usageCollector->collectUsage(
            $usageData,
            $accessKey,
            $user,
            /** @var array<string, mixed> $metadata */
            $metadata
        );

        $this->logger->info('Anthropic token usage collected', [
            'success' => $success,
            'access_key_id' => $accessKey?->getId(),
            'user_id' => $user?->getUserIdentifier(),
            'total_tokens' => $usageData->getTotalTokens(),
            'input_tokens' => $usageData->inputTokens,
            'output_tokens' => $usageData->outputTokens,
            'path' => $request->getPathInfo(),
            'rule_id' => $event->getRule()->getId(),
        ]);
    }

    private function extractUsageFromResponse(string $responseContent): ?AnthropicUsageData
    {
        // 处理流式响应（Server-Sent Events格式）
        if (str_starts_with($responseContent, 'event:') || str_contains($responseContent, 'data:')) {
            return $this->extractUsageFromStreamResponse($responseContent);
        }

        // 处理标准JSON响应
        return $this->extractUsageFromJsonResponse($responseContent);
    }

    private function extractUsageFromStreamResponse(string $streamContent): ?AnthropicUsageData
    {
        $lines = explode("\n", $streamContent);
        $usageData = null;

        foreach ($lines as $line) {
            $extractedUsage = $this->extractUsageFromStreamLine($line);
            if (null !== $extractedUsage && !$extractedUsage->isEmpty()) {
                $usageData = $extractedUsage;
            }
        }

        return $usageData;
    }

    private function extractUsageFromStreamLine(string $line): ?AnthropicUsageData
    {
        $line = trim($line);
        if (!str_starts_with($line, 'data:')) {
            return null;
        }

        $jsonData = trim(substr($line, 5));
        if ('' === $jsonData) {
            return null;
        }

        try {
            $data = json_decode($jsonData, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                return null;
            }

            // 检查根级别的usage字段
            if (isset($data['usage']) && is_array($data['usage'])) {
                /** @var array<string, mixed> $usage */
                $usage = $data['usage'];

                return AnthropicUsageData::fromUsageArray($usage);
            }

            // 检查message.usage字段（用于流式响应的message_start等事件）
            if (isset($data['message']) && is_array($data['message']) && isset($data['message']['usage']) && is_array($data['message']['usage'])) {
                /** @var array<string, mixed> $usage */
                $usage = $data['message']['usage'];

                return AnthropicUsageData::fromUsageArray($usage);
            }
        } catch (\JsonException) {
            // 忽略解析错误
        }

        return null;
    }

    private function extractUsageFromJsonResponse(string $jsonContent): ?AnthropicUsageData
    {
        try {
            $data = json_decode($jsonContent, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data) || !isset($data['usage']) || !is_array($data['usage'])) {
                return null;
            }

            /** @var array<string, mixed> $usage */
            $usage = $data['usage'];

            return AnthropicUsageData::fromUsageArray($usage);
        } catch (\JsonException $e) {
            $this->logger->warning('Failed to parse JSON response', [
                'error' => $e->getMessage(),
                'content_preview' => substr($jsonContent, 0, 200),
            ]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMetadata(Request $request, AfterForwardEvent $event, string $responseContent): array
    {
        $metadata = [
            'endpoint' => $request->getPathInfo(),
            'feature' => 'http_forward',
            'occurTime' => new \DateTimeImmutable(),
            'rule_id' => $event->getRule()->getId(),
            'rule_name' => $event->getRule()->getName(),
            'method' => $request->getMethod(),
        ];

        // 尝试从响应中提取model信息
        if (str_contains($responseContent, '"model"')) {
            $model = $this->extractModelFromResponse($responseContent);
            if (null !== $model) {
                $metadata['model'] = $model;
            }
        }

        // 尝试从响应中提取request_id
        if (str_contains($responseContent, '"id"')) {
            $requestId = $this->extractMessageIdFromResponse($responseContent);
            if (null !== $requestId) {
                $metadata['request_id'] = $requestId;
            }
        }

        return $metadata;
    }

    private function extractModelFromResponse(string $responseContent): ?string
    {
        // 从流式或JSON响应中提取model字段
        if (str_contains($responseContent, 'data:')) {
            // 流式响应
            if (1 === preg_match('/"model"\s*:\s*"([^"]+)"/', $responseContent, $matches)) {
                return $matches[1];
            }
        } else {
            // JSON响应
            try {
                $data = json_decode($responseContent, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($data) && isset($data['model']) && is_string($data['model'])) {
                    return $data['model'];
                }

                return null;
            } catch (\JsonException) {
                // 忽略解析错误
            }
        }

        return null;
    }

    private function extractMessageIdFromResponse(string $responseContent): ?string
    {
        // 从响应中提取message id作为request_id
        if (str_contains($responseContent, 'data:')) {
            // 流式响应
            if (1 === preg_match('/"id"\s*:\s*"(msg_[^"]+)"/', $responseContent, $matches)) {
                return $matches[1];
            }
        } else {
            // JSON响应
            try {
                $data = json_decode($responseContent, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($data) && isset($data['id']) && is_string($data['id'])) {
                    return $data['id'];
                }

                return null;
            } catch (\JsonException) {
                // 忽略解析错误
            }
        }

        return null;
    }
}
