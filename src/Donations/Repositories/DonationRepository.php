<?php

namespace Give\Donations\Repositories;

use Exception;
use Give\Donations\Actions\GeneratePurchaseKey;
use Give\Donations\Models\Donation;
use Give\Donations\ValueObjects\DonationMetaKeys;
use Give\Donations\ValueObjects\DonationMode;
use Give\Framework\Database\DB;
use Give\Framework\Exceptions\Primitives\InvalidArgumentException;
use Give\Framework\Models\ModelQueryBuilder;
use Give\Framework\QueryBuilder\QueryBuilder;
use Give\Framework\Support\Facades\DateTime\Temporal;
use Give\Helpers\Call;
use Give\Helpers\Hooks;
use Give\Log\Log;
use Give\ValueObjects\Money;

/**
 * @unreleased
 */
class DonationRepository
{

    /**
     * @unreleased
     *
     * @var string[]
     */
    private $requiredDonationProperties = [
        'formId',
        'status',
        'gateway',
        'amount',
        'currency',
        'donorId',
        'firstName',
        'email',
    ];

    /**
     * Get Donation By ID
     *
     * @unreleased
     *
     * @param  int  $donationId
     *
     * @return Donation|null
     */
    public function getById($donationId)
    {
        return $this->prepareQuery()
            ->where('ID', $donationId)
            ->get();
    }

    /**
     * @unreleased
     *
     * @param  int  $subscriptionId
     *
     * @return Donation[]|null
     */
    public function getBySubscriptionId($subscriptionId)
    {
        return $this->queryBySubscriptionId($subscriptionId)->getAll();
    }

    /**
     * @unreleased
     *
     * @param  int  $subscriptionId
     *
     * @return ModelQueryBuilder
     */
    public function queryBySubscriptionId($subscriptionId)
    {
        $initialDonationId = give()->subscriptions->getInitialDonationId($subscriptionId);

        $renewals = $this->prepareQuery()
            ->where('post_type', 'give_payment')
            ->where('post_status', 'give_subscription')
            ->whereIn('ID', function (QueryBuilder $builder) use ($subscriptionId) {
                $builder
                    ->select('donation_id')
                    ->from('give_donationmeta')
                    ->where('meta_key', DonationMetaKeys::SUBSCRIPTION_ID)
                    ->where('meta_value', $subscriptionId);
            });

        return $renewals->orWhere('ID', $initialDonationId)->orderBy('post_date', 'DESC');
    }

    /**
     * @unreleased
     *
     * @param  int  $donorId
     *
     * @return ModelQueryBuilder
     */
    public function queryByDonorId($donorId)
    {
        return $this->prepareQuery()
            ->where('post_type', 'give_payment')
            ->whereIn('ID', function (QueryBuilder $builder) use ($donorId) {
                $builder
                    ->select('donation_id')
                    ->from('give_donationmeta')
                    ->where('meta_key', DonationMetaKeys::DONOR_ID)
                    ->where('meta_value', $donorId);
            })
            ->orderBy('post_date', 'DESC');
    }

    /**
     * @unreleased
     *
     * @param  Donation  $donation
     *
     * @return Donation
     * @throws Exception|InvalidArgumentException
     */
    public function insert(Donation $donation)
    {
        $this->validateDonation($donation);

        Hooks::doAction('give_donation_creating', $donation);

        $date = $donation->createdAt ? Temporal::getFormattedDateTime(
            $donation->createdAt
        ) : Temporal::getCurrentFormattedDateForDatabase();


        DB::query('START TRANSACTION');

        try {
            DB::table('posts')
                ->insert([
                    'post_date' => $date,
                    'post_date_gmt' => get_gmt_from_date($date),
                    'post_modified' => $date,
                    'post_modified_gmt' => get_gmt_from_date($date),
                    'post_status' => $donation->status->getValue(),
                    'post_type' => 'give_payment',
                    'post_parent' => isset($donation->parentId) ? $donation->parentId : 0
                ]);

            $donationId = DB::last_insert_id();

            foreach ($this->getCoreDonationMetaForDatabase($donation) as $metaKey => $metaValue) {
                DB::table('give_donationmeta')
                    ->insert([
                        'donation_id' => $donationId,
                        'meta_key' => $metaKey,
                        'meta_value' => $metaValue,
                    ]);
            }
        } catch (Exception $exception) {
            DB::query('ROLLBACK');

            Log::error('Failed creating a donation', compact('donation'));

            throw new $exception('Failed creating a donation');
        }

        DB::query('COMMIT');

        $donation = $this->getById($donationId);

        Hooks::doAction('give_donation_created', $donation);

        return $donation;
    }

    /**
     * @unreleased
     *
     * @param  Donation  $donation
     *
     * @return Donation
     * @throws Exception|InvalidArgumentException
     */
    public function update(Donation $donation)
    {
        $this->validateDonation($donation);

        Hooks::doAction('give_donation_updating', $donation);

        $date = Temporal::getCurrentFormattedDateForDatabase();

        DB::query('START TRANSACTION');

        try {
            DB::table('posts')
                ->where('ID', $donation->id)
                ->update([
                    'post_modified' => $date,
                    'post_modified_gmt' => get_gmt_from_date($date),
                    'post_status' => $donation->status->getValue(),
                    'post_type' => 'give_payment',
                    'post_parent' => isset($donation->parentId) ? $donation->parentId : 0
                ]);

            foreach ($this->getCoreDonationMetaForDatabase($donation) as $metaKey => $metaValue) {
                DB::table('give_donationmeta')
                    ->where('donation_id', $donation->id)
                    ->where('meta_key', $metaKey)
                    ->update([
                        'meta_value' => $metaValue,
                    ]);
            }
        } catch (Exception $exception) {
            DB::query('ROLLBACK');

            Log::error('Failed updating a donation', compact('donation'));

            throw new $exception('Failed updating a donation');
        }

        DB::query('COMMIT');

        Hooks::doAction('give_donation_updated', $donation);

        return $donation;
    }

    /**
     * @unreleased
     *
     * @param  Donation  $donation
     * @return bool
     * @throws Exception
     */
    public function delete(Donation $donation)
    {
        DB::query('START TRANSACTION');

        Hooks::doAction('give_donation_deleting', $donation);

        try {
            DB::table('posts')
                ->where('id', $donation->id)
                ->delete();

            foreach ($this->getCoreDonationMetaForDatabase($donation) as $metaKey => $metaValue) {
                DB::table('give_donationmeta')
                    ->where('donation_id', $donation->id)
                    ->where('meta_key', $metaKey)
                    ->delete();
            }
        } catch (Exception $exception) {
            DB::query('ROLLBACK');

            Log::error('Failed deleting a donation', compact('donation'));

            throw new $exception('Failed deleting a donation');
        }

        DB::query('COMMIT');

        Hooks::doAction('give_donation_deleted', $donation);

        return true;
    }

    /**
     * @unreleased
     *
     * @param  Donation  $donation
     *
     * @return array
     */
    private function getCoreDonationMetaForDatabase(Donation $donation)
    {
        $meta = [
            DonationMetaKeys::AMOUNT => Money::of($donation->amount, $donation->currency)->getAmount(),
            DonationMetaKeys::CURRENCY => $donation->currency,
            DonationMetaKeys::GATEWAY => $donation->gateway,
            DonationMetaKeys::DONOR_ID => $donation->donorId,
            DonationMetaKeys::FIRST_NAME => $donation->firstName,
            DonationMetaKeys::LAST_NAME => $donation->lastName,
            DonationMetaKeys::EMAIL => $donation->email,
            DonationMetaKeys::FORM_ID => $donation->formId,
            DonationMetaKeys::FORM_TITLE => isset($donation->formTitle) ? $donation->formTitle : $this->getFormTitle(
                $donation->formId
            ),
            DonationMetaKeys::MODE => isset($donation->mode) ? $donation->mode->getValue(
            ) : $this->getDefaultDonationMode()->getValue(),
            DonationMetaKeys::PURCHASE_KEY => isset($donation->purchaseKey)
                ? $donation->purchaseKey
                : Call::invoke(
                    GeneratePurchaseKey::class,
                    $donation->email
                ),
            DonationMetaKeys::DONOR_IP => isset($donation->donorIp) ? $donation->donorIp : give_get_ip(),
        ];

        if (isset($donation->billingAddress)) {
            $meta[DonationMetaKeys::BILLING_COUNTRY] = $donation->billingAddress->country;
            $meta[DonationMetaKeys::BILLING_ADDRESS2] = $donation->billingAddress->address2;
            $meta[DonationMetaKeys::BILLING_CITY] = $donation->billingAddress->city;
            $meta[DonationMetaKeys::BILLING_ADDRESS1] = $donation->billingAddress->address1;
            $meta[DonationMetaKeys::BILLING_STATE] = $donation->billingAddress->state;
            $meta[DonationMetaKeys::BILLING_ZIP] = $donation->billingAddress->zip;
        }

        if (isset($donation->subscriptionId)) {
            $meta[DonationMetaKeys::SUBSCRIPTION_ID] = $donation->subscriptionId;
        }

        if (isset($donation->anonymous)) {
            $meta[DonationMetaKeys::ANONYMOUS] = $donation->anonymous;
        }

        if (isset($donation->levelId)) {
            $meta[DonationMetaKeys::LEVEL_ID] = $donation->levelId;
        }

        return $meta;
    }

    /**
     * In Legacy terms, the Initial Donation acts as the parent ID for subscription renewals.
     * This function inserts those specific meta columns that accompany this concept.
     *
     * @unreleased
     *
     * @throws Exception
     */
    public function updateLegacyDonationMetaAsInitialSubscriptionDonation($donationId)
    {
        DB::query('START TRANSACTION');

        try {
            DB::table('give_donationmeta')
                ->insert(
                    [
                        'donation_id' => $donationId,
                        'meta_key' => '_give_subscription_payment',
                        'meta_value' => true,
                    ]
                );

            DB::table('give_donationmeta')
                ->insert(
                    [
                        'donation_id' => $donationId,
                        'meta_key' => '_give_is_donation_recurring',
                        'meta_value' => true,
                    ]
                );
        } catch (Exception $exception) {
            DB::query('ROLLBACK');

            Log::error('Failed updating a donation as initial legacy subscription donation', compact('donationId'));

            throw new $exception('Failed updating a donation as initial legacy subscription donation');
        }

        DB::query('COMMIT');

        return true;
    }

    /**
     *
     * @unreleased
     *
     * @param  int  $donationId
     *
     * @return int|null
     */
    public function getSequentialId($donationId)
    {
        $query = DB::table('give_sequential_ordering')->where('payment_id', $donationId)->get();

        if (!$query) {
            return null;
        }

        return (int)$query->id;
    }

    /**
     * @unreleased
     *
     * @param  int  $id
     *
     * @return object[]
     */
    public function getNotesByDonationId($id)
    {
        $notes = DB::table('give_comments')
            ->select(
                ['comment_content', 'note'],
                ['comment_date', 'date']
            )
            ->where('comment_parent', $id)
            ->where('comment_type', 'donation')
            ->orderBy('comment_date', 'DESC')
            ->getAll();

        if (!$notes) {
            return [];
        }

        return $notes;
    }

    /**
     * @unreleased
     *
     * @param  Donation  $donation
     * @return void
     */
    private function validateDonation(Donation $donation)
    {
        foreach ($this->requiredDonationProperties as $key) {
            if (!isset($donation->$key)) {
                throw new InvalidArgumentException("'$key' is required.");
            }
        }

        if (!$donation->donor) {
            throw new InvalidArgumentException("Invalid donorId, Donor does not exist");
        }
    }

    /**
     * @unreleased
     *
     * @return DonationMode
     */
    private function getDefaultDonationMode()
    {
        $mode = give_is_test_mode() ? 'test' : 'live';

        return new DonationMode($mode);
    }

    /**
     * @unreleased
     *
     * @param  int  $formId
     * @return string
     */
    public function getFormTitle($formId)
    {
        $form = DB::table('posts')
            ->where('id', $formId)
            ->get();

        if (!$form) {
            return '';
        }

        return $form->post_title;
    }

    /**
     * @return ModelQueryBuilder<Donation>
     */
    public function prepareQuery()
    {
        $builder = new ModelQueryBuilder(Donation::class);

        return $builder->from('posts')
            ->select(
                ['ID', 'id'],
                ['post_date', 'createdAt'],
                ['post_modified', 'updatedAt'],
                ['post_status', 'status'],
                ['post_parent', 'parentId']
            )
            ->attachMeta(
                'give_donationmeta',
                'ID',
                'donation_id',
                ...DonationMetaKeys::getColumnsForAttachMetaQuery()
            );
    }

    /**
     * @unreleased
     *
     * @param $donorId
     * @return int
     */
    public function getTotalDonationCountByDonorId($donorId)
    {
        return (int)DB::table('posts')
            ->where('post_type', 'give_payment')
            ->whereIn('ID', function (QueryBuilder $builder) use ($donorId) {
                $builder
                    ->select('donation_id')
                    ->from('give_donationmeta')
                    ->where('meta_key', DonationMetaKeys::DONOR_ID)
                    ->where('meta_value', $donorId);
            })
            ->count();
    }

    /**
     * @unreleased
     *
     * @param $donorId
     * @return array|bool|null
     */
    public function getAllDonationIdsByDonorId($donorId)
    {
        return array_column(
            DB::table('give_donationmeta')
                ->select('donation_id')
                ->where('meta_key', DonationMetaKeys::DONOR_ID)
                ->where('meta_value', $donorId)
                ->getAll(),
            'donation_id'
        );
    }
}
