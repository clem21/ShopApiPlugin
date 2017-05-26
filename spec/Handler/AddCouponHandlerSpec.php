<?php

namespace spec\Sylius\ShopApiPlugin\Handler;

use Prophecy\Argument;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PromotionCouponInterface;
use Sylius\Component\Core\Model\PromotionInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Promotion\Checker\Eligibility\PromotionCouponEligibilityCheckerInterface;
use Sylius\Component\Promotion\Checker\Eligibility\PromotionEligibilityCheckerInterface;
use Sylius\Component\Promotion\Repository\PromotionCouponRepositoryInterface;
use Sylius\ShopApiPlugin\Command\AddCoupon;
use PhpSpec\ObjectBehavior;
use Sylius\ShopApiPlugin\Handler\AddCouponHandler;

final class AddCouponHandlerSpec extends ObjectBehavior
{
    function let(
        OrderRepositoryInterface $orderRepository,
        PromotionCouponRepositoryInterface $couponRepository,
        OrderProcessorInterface $orderProcessor,
        PromotionCouponEligibilityCheckerInterface $couponEligibilityChecker
    ) {
        $this->beConstructedWith($orderRepository, $couponRepository, $orderProcessor, $couponEligibilityChecker);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(AddCouponHandler::class);
    }

    function it_handles_adding_coupon_to_cart(
        ChannelInterface $channel,
        PromotionCouponInterface $coupon,
        PromotionCouponRepositoryInterface $couponRepository,
        PromotionInterface $promotion,
        OrderInterface $order,
        OrderProcessorInterface $orderProcessor,
        OrderRepositoryInterface $orderRepository,
        PromotionCouponEligibilityCheckerInterface $couponEligibilityChecker,
        PromotionEligibilityCheckerInterface $promotionEligibilityChecker
    ) {
        $orderRepository->findOneBy(['tokenValue' => 'ORDERTOKEN'])->willReturn($order);
        $couponRepository->findOneBy(['code' => 'COUPON_CODE'])->willReturn($coupon);

        $coupon->getPromotion()->willReturn($promotion);
        $order->getChannel()->willReturn($channel);
        $promotion->hasChannel($channel)->willReturn(true);

        $couponEligibilityChecker->isEligible($order, $coupon)->willReturn(true);
        $promotionEligibilityChecker->isEligible($order, $promotion)->willReturn(true);

        $order->setPromotionCoupon($coupon)->shouldBeCalled();
        $orderProcessor->process($order)->shouldBeCalled();

        $this->handle(new AddCoupon('ORDERTOKEN', 'COUPON_CODE'));
    }

    function it_throws_an_exception_if_order_does_not_exist(
        OrderRepositoryInterface $orderRepository
    ) {
        $orderRepository->findOneBy(['tokenValue' => 'ORDERTOKEN'])->willReturn(null);

        $this->shouldThrow(\InvalidArgumentException::class)->during('handle', [
            new AddCoupon('ORDERTOKEN', 'COUPON_CODE'),
        ]);
    }

    function it_throws_an_exception_if_coupon_does_not_exist(
        PromotionCouponRepositoryInterface $couponRepository,
        OrderInterface $order,
        OrderRepositoryInterface $orderRepository
    ) {
        $orderRepository->findOneBy(['tokenValue' => 'ORDERTOKEN'])->willReturn($order);
        $couponRepository->findOneBy(['code' => 'COUPON_CODE'])->willReturn(null);

        $this->shouldThrow(\InvalidArgumentException::class)->during('handle', [
            new AddCoupon('ORDERTOKEN', 'COUPON_CODE'),
        ]);
    }

    function it_throws_an_exception_if_order_is_not_eligible_for_coupon(
        PromotionCouponInterface $coupon,
        PromotionCouponRepositoryInterface $couponRepository,
        OrderInterface $order,
        OrderRepositoryInterface $orderRepository,
        PromotionCouponEligibilityCheckerInterface $couponEligibilityChecker
    ) {
        $orderRepository->findOneBy(['tokenValue' => 'ORDERTOKEN'])->willReturn($order);
        $couponRepository->findOneBy(['code' => 'COUPON_CODE'])->willReturn($coupon);

        $couponEligibilityChecker->isEligible($order, $coupon)->willReturn(false);

        $this->shouldThrow(\InvalidArgumentException::class)->during('handle', [
            new AddCoupon('ORDERTOKEN', 'COUPON_CODE'),
        ]);
    }
}