<?php

namespace WholesaleOrdering\Tests;

use WholesaleOrdering\Customers\WholesaleStatus;
use WholesaleOrdering\Infrastructure\Config;
use WholesaleOrdering\Infrastructure\RoleManager;
use WholesaleOrdering\Pricing\CustomerContext;
use WholesaleOrdering\Pricing\PricingService;
use WholesaleOrdering\Products\ProductFields;
use WP_UnitTestCase;

/**
 * Tests the V1 configured Wholesale Price pricing model.
 *
 * V1 pricing contract:
 *
 * - Guests/non-approved customers receive Regular Price.
 * - Approved wholesale customers receive configured Wholesale Price.
 * - Missing Wholesale Price falls back to Regular Price.
 * - Invalid Wholesale Price falls back to Regular Price.
 * - Configured Wholesale Price is authoritative.
 * - No additional percentage discount is calculated by PricingService.
 *
 * @package WholesaleOrdering
 */
final class PricingServiceTest extends WP_UnitTestCase {

    /**
     * Test product ID.
     */
    private int $product_id = 0;

    /**
     * Approved wholesale user ID.
     */
    private int $approved_user_id = 0;

    /**
     * Pricing service.
     */
    private PricingService $pricing_service;

    /**
     * Set up test fixtures.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();

        $this->pricing_service = new PricingService();

        $this->ensure_wholesale_role_exists();

        $this->product_id = self::factory()->post->create(
            array(
                'post_type'   => 'product',
                'post_status' => 'publish',
            )
        );

        $product = new \WC_Product_Simple(
            $this->product_id
        );

        $product->set_regular_price( '100.00' );
        $product->set_price( '100.00' );
        $product->save();

        $this->approved_user_id = self::factory()->user->create(
            array(
                'role' => 'customer',
            )
        );

        $this->grant_wholesale_eligibility(
            $this->approved_user_id
        );
    }

    /**
     * Tear down test fixtures.
     *
     * @return void
     */
    protected function tearDown(): void {
        wp_set_current_user( 0 );

        if ( $this->product_id > 0 ) {
            wp_delete_post(
                $this->product_id,
                true
            );
        }

        if ( $this->approved_user_id > 0 ) {
            wp_delete_user(
                $this->approved_user_id
            );
        }

        parent::tearDown();
    }

    /**
     * 100 + configured wholesale 90 -> 90.
     *
     * @return void
     */
    public function test_100_regular_and_90_wholesale_returns_90(): void {
        $product = $this->get_product();

        $this->set_wholesale_price(
            $product,
            '90.00'
        );

        $context = new CustomerContext(
            $this->approved_user_id
        );

        $this->assertSame(
            '90.00',
            $this->pricing_service->getEligiblePrice(
                $product,
                $context
            )
        );
    }

    /**
     * 100 + configured wholesale 87.50 -> 87.50.
     *
     * @return void
     */
    public function test_100_regular_and_87_50_wholesale_returns_87_50(): void {
        $product = $this->get_product();

        $this->set_wholesale_price(
            $product,
            '87.50'
        );

        $context = new CustomerContext(
            $this->approved_user_id
        );

        $this->assertSame(
            '87.50',
            $this->pricing_service->getEligiblePrice(
                $product,
                $context
            )
        );
    }

    /**
     * 100 + configured wholesale 96 -> 96, not 98.
     *
     * This protects the configured Wholesale Price from an accidental
     * 2% calculation.
     *
     * @return void
     */
    public function test_100_regular_and_96_wholesale_returns_96_not_98(): void {
        $product = $this->get_product();

        $this->set_wholesale_price(
            $product,
            '96.00'
        );

        $context = new CustomerContext(
            $this->approved_user_id
        );

        $price = $this->pricing_service->getEligiblePrice(
            $product,
            $context
        );

        $this->assertSame(
            '96.00',
            $price
        );

        $this->assertNotSame(
            '98.00',
            $price
        );
    }

    /**
     * 100 + configured wholesale 90 -> 90, not 86.40.
     *
     * This protects the configured Wholesale Price from an accidental
     * additional 4% calculation.
     *
     * @return void
     */
    public function test_100_regular_and_90_wholesale_returns_90_not_86_40(): void {
        $product = $this->get_product();

        $this->set_wholesale_price(
            $product,
            '90.00'
        );

        $context = new CustomerContext(
            $this->approved_user_id
        );

        $price = $this->pricing_service->getEligiblePrice(
            $product,
            $context
        );

        $this->assertSame(
            '90.00',
            $price
        );

        $this->assertNotSame(
            '86.40',
            $price
        );
    }

    /**
     * Missing Wholesale Price -> Regular Price.
     *
     * @return void
     */
    public function test_missing_wholesale_price_returns_100(): void {
        $product = $this->get_product();

        delete_post_meta(
            $product->get_id(),
            ProductFields::META_WHOLESALE_PRICE
        );

        $context = new CustomerContext(
            $this->approved_user_id
        );

        $this->assertSame(
            '100.00',
            $this->pricing_service->getEligiblePrice(
                $product,
                $context
            )
        );
    }

    /**
     * Invalid Wholesale Price -> Regular Price.
     *
     * @return void
     */
    public function test_invalid_wholesale_price_returns_100(): void {
        $product = $this->get_product();

        $this->set_wholesale_price(
            $product,
            'not-a-price'
        );

        $context = new CustomerContext(
            $this->approved_user_id
        );

        $this->assertSame(
            '100.00',
            $this->pricing_service->getEligiblePrice(
                $product,
                $context
            )
        );
    }

    /**
     * Negative Wholesale Price -> Regular Price.
     *
     * @return void
     */
    public function test_negative_wholesale_price_returns_100(): void {
        $product = $this->get_product();

        $this->set_wholesale_price(
            $product,
            '-10.00'
        );

        $context = new CustomerContext(
            $this->approved_user_id
        );

        $this->assertSame(
            '100.00',
            $this->pricing_service->getEligiblePrice(
                $product,
                $context
            )
        );
    }

    /**
     * Unapproved customer -> Regular Price.
     *
     * @return void
     */
    public function test_unapproved_customer_returns_100(): void {
        $product = $this->get_product();

        $this->set_wholesale_price(
            $product,
            '90.00'
        );

        $user_id = self::factory()->user->create(
            array(
                'role' => 'customer',
            )
        );

        $context = new CustomerContext(
            $user_id
        );

        $this->assertFalse(
            $context->can_use_wholesale_pricing()
        );

        $this->assertSame(
            '100.00',
            $this->pricing_service->getEligiblePrice(
                $product,
                $context
            )
        );

        wp_delete_user(
            $user_id
        );
    }

    /**
     * Approved customer -> configured Wholesale Price.
     *
     * @return void
     */
    public function test_approved_customer_receives_configured_wholesale_price(): void {
        $product = $this->get_product();

        $this->set_wholesale_price(
            $product,
            '90.00'
        );

        $context = new CustomerContext(
            $this->approved_user_id
        );

        $this->assertTrue(
            $context->can_use_wholesale_pricing()
        );

        $this->assertSame(
            '90.00',
            $this->pricing_service->getEligiblePrice(
                $product,
                $context
            )
        );
    }

    /**
     * Zero Wholesale Price remains a valid configured price.
     *
     * @return void
     */
    public function test_zero_wholesale_price_is_valid(): void {
        $product = $this->get_product();

        $this->set_wholesale_price(
            $product,
            '0.00'
        );

        $context = new CustomerContext(
            $this->approved_user_id
        );

        $this->assertSame(
            '0.00',
            $this->pricing_service->getEligiblePrice(
                $product,
                $context
            )
        );
    }

    /**
     * Get the test product.
     *
     * @return \WC_Product
     */
    private function get_product(): \WC_Product {
        $product = wc_get_product(
            $this->product_id
        );

        $this->assertInstanceOf(
            \WC_Product::class,
            $product
        );

        return $product;
    }

    /**
     * Set Wholesale Price metadata.
     *
     * @param \WC_Product $product Product.
     * @param string      $price   Wholesale Price.
     *
     * @return void
     */
    private function set_wholesale_price(
        \WC_Product $product,
        string $price
    ): void {
        update_post_meta(
            $product->get_id(),
            ProductFields::META_WHOLESALE_PRICE,
            $price
        );
    }

    /**
     * Ensure the wholesale role exists.
     *
     * @return void
     */
    private function ensure_wholesale_role_exists(): void {
        $role = get_role(
            RoleManager::ROLE
        );

        if ( null !== $role ) {
            return;
        }

        add_role(
            RoleManager::ROLE,
            'Approved Wholesale Customer',
            array(
                'read' => true,
                RoleManager::CAPABILITY => true,
            )
        );
    }

    /**
     * Grant the exact status/capability combination required for wholesale
     * pricing.
     *
     * @param int $user_id User ID.
     *
     * @return void
     */
    private function grant_wholesale_eligibility(
        int $user_id
    ): void {
        $user = new \WP_User(
            $user_id
        );

        $user->add_role(
            RoleManager::ROLE
        );

        WholesaleStatus::set(
            $user_id,
            Config::STATUS_APPROVED
        );
    }
}