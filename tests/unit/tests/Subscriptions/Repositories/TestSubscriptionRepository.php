<?php

namespace unit\tests\Subscriptions\Repositories;

use Exception;
use Give\Framework\Database\DB;
use Give\Framework\Models\Traits\InteractsWithTime;
use Give\Subscriptions\Models\Subscription;
use Give\Subscriptions\Repositories\SubscriptionRepository;
use Give\Subscriptions\ValueObjects\SubscriptionPeriod;
use Give\Subscriptions\ValueObjects\SubscriptionStatus;
use Give_Subscriptions_DB;
use Give_Unit_Test_Case;

/**
 * @unreleased
 *
 * @coversDefaultClass SubscriptionRepository
 */
class TestSubscriptionRepository extends Give_Unit_Test_Case
{
    use InteractsWithTime;

    public function setUp()
    {
        parent::setUp();

        /** @var Give_Subscriptions_DB $legacySubscriptionDb */
        $legacySubscriptionDb = give(Give_Subscriptions_DB::class);

        $legacySubscriptionDb->create_table();
    }

    /**
     * @unreleased - truncate donationMetaTable to avoid duplicate records
     *
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();
        $donationMetaTable = DB::prefix('give_donationmeta');
        //$subscriptionMetaTable = DB::prefix('give_subscriptionmeta');

        DB::query("TRUNCATE TABLE $donationMetaTable");
        //DB::query("TRUNCATE TABLE $subscriptionMetaTable");
    }

    /**
     * @unreleased
     *
     * @return void
     *
     * @throws Exception
     */
    public function testGetByIdShouldReturnSubscription()
    {
        $subscriptionInstance = $this->createSubscriptionInstance();
        $repository = new SubscriptionRepository();

        /** @var Subscription $insertedSubscription */
        $insertedSubscription = $repository->insert($subscriptionInstance);

        $subscriptionQuery = DB::table('give_subscriptions')
            ->where('id', $insertedSubscription->id)
            ->get();

        $this->assertInstanceOf(Subscription::class, $insertedSubscription);
        $this->assertEquals($insertedSubscription->id, $subscriptionQuery->id);
    }

    /**
     * @unreleased
     *
     * @return void
     * @throws Exception
     */
    public function testInsertShouldAddSubscriptionToDatabase()
    {
        $subscriptionInstance = $this->createSubscriptionInstance();
        $repository = new SubscriptionRepository();

        /** @var Subscription $insertedSubscription */
        $insertedSubscription = $repository->insert($subscriptionInstance);

        $subscriptionQuery = DB::table('give_subscriptions')
            ->where('id', $insertedSubscription->id)
            ->get();

        $this->assertInstanceOf(Subscription::class, $insertedSubscription);
        $this->assertEquals($this->toDateTime($subscriptionQuery->created), $insertedSubscription->createdAt);
        $this->assertEquals($subscriptionQuery->customer_id, $insertedSubscription->donorId);
        $this->assertEquals($subscriptionQuery->profile_id, $insertedSubscription->gatewaySubscriptionId);
        $this->assertEquals($subscriptionQuery->product_id, $insertedSubscription->donationFormId);
        $this->assertEquals($subscriptionQuery->period, $insertedSubscription->period->getValue());
        $this->assertEquals($subscriptionQuery->frequency, $insertedSubscription->frequency);
        $this->assertEquals($subscriptionQuery->initial_amount, $insertedSubscription->amount);
        $this->assertEquals($subscriptionQuery->recurring_amount, $insertedSubscription->amount);
        $this->assertEquals($subscriptionQuery->recurring_fee_amount, $insertedSubscription->feeAmount);
        $this->assertEquals($subscriptionQuery->bill_times, $insertedSubscription->installments);
        $this->assertEquals($subscriptionQuery->transaction_id, $insertedSubscription->transactionId);
        $this->assertEquals($subscriptionQuery->status, $insertedSubscription->status->getValue());
    }

    /**
     * @unreleased
     *
     * @return Subscription
     */
    private function createSubscriptionInstance()
    {
        return new Subscription([
            'id' => 1,
            'createdAt' => $this->getCurrentDateTime(),
            'amount' => 50,
            'period' => SubscriptionPeriod::MONTH(),
            'frequency' => 1,
            'donorId' => 1,
            'installments' => 0,
            'transactionId' => 'transaction-id',
            'feeAmount' => 0,
            'status' => SubscriptionStatus::PENDING(),
            'gatewaySubscriptionId' => 'gateway-subscription-id',
            'donationFormId' => 1
        ]);
    }
}
