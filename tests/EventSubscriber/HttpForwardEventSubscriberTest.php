<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\EventSubscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\AccessKeyBundle\Entity\AccessKey;
use Tourze\AnthropicTokenUsageBundle\EventSubscriber\HttpForwardEventSubscriber;
use Tourze\AnthropicTokenUsageBundle\Interface\UsageCollectorInterface;
use Tourze\AnthropicTokenUsageBundle\ValueObject\AnthropicUsageData;
use Tourze\AnthropicTokenUsageBundle\ValueObject\BatchProcessResult;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageCollectionBatch;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Entity\ForwardRule;
use Tourze\HttpForwardBundle\Event\AfterForwardEvent;
use Tourze\PHPUnitSymfonyKernelTest\AbstractEventSubscriberTestCase;

/**
 * HttpForwardEventSubscriber集成测试
 * 测试重点：事件处理逻辑、Usage数据提取、错误处理
 * @internal
 */
#[CoversClass(HttpForwardEventSubscriber::class)]
#[RunTestsInSeparateProcesses]
final class HttpForwardEventSubscriberTest extends AbstractEventSubscriberTestCase
{
    private UsageCollectorInterface $mockUsageCollector;

    private HttpForwardEventSubscriber $subscriber;

    protected function onSetUp(): void
    {
        $this->createTestDoubles();
        $this->injectMocksIntoContainer();
        $this->subscriber = self::getService(HttpForwardEventSubscriber::class);
    }

    private function createTestDoubles(): void
    {
        $this->mockUsageCollector = new class () implements UsageCollectorInterface {
            /** @var array<mixed> */
            public array $calls = [];

            public bool $returnValue = true;

            public function collectUsage(
                AnthropicUsageData $usageData,
                ?AccessKey $accessKey = null,
                ?UserInterface $user = null,
                array $metadata = [],
            ): bool {
                $this->calls[] = func_get_args();

                return $this->returnValue;
            }

            public function collectBatchUsage(UsageCollectionBatch $batch): BatchProcessResult
            {
                throw new \RuntimeException('Not implemented');
            }

            public function collectUsageSync(
                AnthropicUsageData $usageData,
                ?AccessKey $accessKey = null,
                ?UserInterface $user = null,
                array $metadata = [],
            ): bool {
                throw new \RuntimeException('Not implemented');
            }
        };
    }

    private function injectMocksIntoContainer(): void
    {
        // 替换UsageCollectorInterface服务
        self::getContainer()->set('tourze.anthropic_token_usage.usage_collector', $this->mockUsageCollector);
        self::getContainer()->set(UsageCollectorInterface::class, $this->mockUsageCollector);
        // 不再直接替换全局logger，WithMonologChannel注解会自动配置专用logger
    }

    public function testGetSubscribedEvents(): void
    {
        $events = HttpForwardEventSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey('http_forward.after_forward', $events);
        $this->assertSame('onAfterForward', $events['http_forward.after_forward']);
    }

    public function testOnAfterForwardWithNonSuccessfulResponse(): void
    {
        $request = Request::create('/test', 'POST');
        $response = new Response('error', 500);
        $rule = new ForwardRule();
        $event = new AfterForwardEvent($request, $response, $rule);

        // Assert that collectUsage is never called by checking calls array
        $initialCallsCount = $this->getMockCallsCount();

        $this->subscriber->onAfterForward($event);

        // 验证非成功响应不会触发 usage 收集
        // @phpstan-ignore property.notFound
        $calls = $this->mockUsageCollector->calls;
        // 移除冗余的 assertIsArray,因为 $calls 属性已明确声明为 array 类型
        $this->assertCount($initialCallsCount, $calls);
    }

    public function testOnAfterForwardWithNonAnthropicApi(): void
    {
        $request = Request::create('/some/other/api', 'POST');
        $response = new Response('success', 200);
        $rule = new ForwardRule();
        $event = new AfterForwardEvent($request, $response, $rule);

        // Assert that collectUsage is never called by checking calls array
        $initialCallsCount = $this->getMockCallsCount();

        $this->subscriber->onAfterForward($event);

        // 验证非 Anthropic API 路径不会触发 usage 收集
        // @phpstan-ignore property.notFound
        $calls = $this->mockUsageCollector->calls;
        // 移除冗余的 assertIsArray,因为 $calls 属性已明确声明为 array 类型
        $this->assertCount($initialCallsCount, $calls);
    }

    public function testOnAfterForwardWithAnthropicApiAndUsageData(): void
    {
        $responseBody = json_encode([
            'usage' => [
                'input_tokens' => 100,
                'cache_creation_input_tokens' => 50,
                'cache_read_input_tokens' => 25,
                'output_tokens' => 75,
            ],
            'model' => 'claude-3-opus',
            'id' => 'msg_123456',
        ], JSON_THROW_ON_ERROR);

        $request = Request::create('/api/v1/messages', 'POST');
        $response = new Response($responseBody, 200);
        $rule = new ForwardRule();
        $accessKey = new AccessKey();
        $forwardLog = new ForwardLog();
        $forwardLog->setAccessKey($accessKey);

        $event = new AfterForwardEvent($request, $response, $rule, $forwardLog);

        $initialCallsCount = $this->getMockCallsCount();
        // @phpstan-ignore property.notFound
        $this->mockUsageCollector->returnValue = true;

        $this->subscriber->onAfterForward($event);

        // 验证 Anthropic API 响应成功触发 usage 收集
        // @phpstan-ignore property.notFound
        $calls = $this->mockUsageCollector->calls;
        // 移除冗余的 assertIsArray,因为 $calls 属性已明确声明为 array 类型
        $this->assertCount($initialCallsCount + 1, $calls);
    }

    public function testOnAfterForwardWithEmptyResponse(): void
    {
        $request = Request::create('/api/v1/messages', 'POST');
        $response = new Response('', 200);
        $rule = new ForwardRule();
        $event = new AfterForwardEvent($request, $response, $rule);

        $initialUsageCallsCount = $this->getMockCallsCount();

        $this->subscriber->onAfterForward($event);

        // 验证 UsageCollector 没有被调用（因为响应为空）
        // @phpstan-ignore property.notFound
        $calls = $this->mockUsageCollector->calls;
        // 移除冗余的 assertIsArray,因为 $calls 属性已明确声明为 array 类型
        $this->assertCount($initialUsageCallsCount, $calls);
        // 注意：不再验证logger调用，因为我们不控制WithMonologChannel配置的logger
    }

    public function testOnAfterForwardWithStreamResponse(): void
    {
        $streamResponse = "event: message_start\ndata: {\"type\":\"message_start\",\"message\":{\"usage\":{\"input_tokens\":10,\"cache_creation_input_tokens\":0,\"cache_read_input_tokens\":0,\"output_tokens\":1}}}\n\nevent: message_stop\ndata: {\"type\":\"message_stop\"}";

        $request = Request::create('/api/v1/messages', 'POST');
        $response = new Response($streamResponse, 200);
        $rule = new ForwardRule();
        $accessKey = new AccessKey();
        $forwardLog = new ForwardLog();
        $forwardLog->setAccessKey($accessKey);

        $event = new AfterForwardEvent($request, $response, $rule, $forwardLog);

        $initialCallsCount = $this->getMockCallsCount();
        // @phpstan-ignore property.notFound
        $this->mockUsageCollector->returnValue = true;

        $this->subscriber->onAfterForward($event);

        // 验证流式响应成功触发 usage 收集
        // @phpstan-ignore property.notFound
        $calls = $this->mockUsageCollector->calls;
        // 移除冗余的 assertIsArray,因为 $calls 属性已明确声明为 array 类型
        $this->assertCount($initialCallsCount + 1, $calls);
    }

    /**
     * 类型安全地获取 mock collector 调用次数
     *
     * @phpstan-impure
     */
    private function getMockCallsCount(): int
    {
        // @phpstan-ignore property.notFound
        $calls = $this->mockUsageCollector->calls;
        $this->assertIsArray($calls);
        return count($calls);
    }
}
