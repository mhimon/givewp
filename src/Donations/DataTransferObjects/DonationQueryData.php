<?php

namespace Give\Donations\DataTransferObjects;

use DateTime;
use Give\Donations\Models\Donation;
use Give\Donations\ValueObjects\DonationStatus;
use Give\Framework\Models\Traits\InteractsWithTime;

/**
 * Class DonationData
 *
 * @unreleased
 */
class DonationQueryData
{
    use InteractsWithTime;
    /**
     * @var int
     */
    private $amount;
    /**
     * @var string
     */
    private $currency;
    /**
     * @var int
     */
    private $donorId;
    /**
     * @var string
     */
    private $firstName;
    /**
     * @var string
     */
    private $lastName;
    /**
     * @var string
     */
    private $email;
    /**
     * @var int
     */
    private $id;
    /**
     * @var DonationStatus
     */
    private $status;
    /**
     * @var int
     */
    private $parentId;
    /**
     * @var int
     */
    private $subscriptionId;
    /**
     * @var DateTime
     */
    private $updatedAt;
    /**
     * @var DateTime
     */
    private $createdAt;
    /**
     * @var string
     */
    private $gateway;

    /**
     * Convert data from object to Donation
     *
     * @param  object  $donationQueryObject
     *
     * @unreleased
     *
     * @return self
     */
    public static function fromObject($donationQueryObject)
    {
        $self = new static();

        $self->amount = (int)$donationQueryObject->amount;
        $self->currency = $donationQueryObject->currency;
        $self->donorId = (int)$donationQueryObject->donorId;
        $self->firstName = $donationQueryObject->firstName;
        $self->lastName = $donationQueryObject->lastName;
        $self->email = $donationQueryObject->email;
        $self->id = (int)$donationQueryObject->id;
        $self->gateway = $donationQueryObject->gateway;
        $self->createdAt = $self->toDateTime($donationQueryObject->createdAt);
        $self->updatedAt = $self->toDateTime($donationQueryObject->updatedAt);
        $self->status = new DonationStatus($donationQueryObject->status);
        $self->parentId = (int)$donationQueryObject->parentId;
        $self->subscriptionId = (int)$donationQueryObject->subscriptionId;

        return $self;
    }

    /**
     * Convert DTO to Donation
     *
     * @return Donation
     */
    public function toDonation()
    {
        return new Donation(
            [
                'id' => $this->id,
                'createdAt' => $this->createdAt,
                'updatedAt' => $this->updatedAt,
                'status' => $this->status,
                'gateway' => $this->gateway,
                'amount' => $this->amount,
                'currency' => $this->currency,
                'donorId' => $this->donorId,
                'firstName' => $this->firstName,
                'lastName' => $this->lastName,
                'email' => $this->email,
                'parentId' => $this->parentId,
                'subscriptionId' => $this->subscriptionId
            ]
        );
    }
}
