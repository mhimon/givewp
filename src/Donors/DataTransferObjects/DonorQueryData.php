<?php

namespace Give\Donors\DataTransferObjects;

use Give\Donors\Models\Donor;
use Give\Framework\Support\Facades\DateTime\Temporal;

/**
 * Class DonorObjectData
 *
 * @unreleased
 */
class DonorQueryData
{

    /**
     * @var int
     */
    public $id;
    /**
     * @var string
     */
    public $createdAt;
    /**
     * @var int
     */
    public $userId;
    /**
     * @var string
     */
    public $email;
    /**
     * @var string
     */
    public $name;
    /**
     * @var string
     */
    public $firstName;
    /**
     * @var string
     */
    public $lastName;
    /**
     * @var mixed
     */
    public $additionalEmails;

    /**
     * Convert data from donor object to Donor Model
     *
     * @unreleased
     *
     * @return self
     */
    public static function fromObject($object)
    {
        $self = new static();

        $self->id = (int)$object->id;
        $self->userId = (int)$object->userId;
        $self->email = $object->email;
        $self->name = $object->name;
        $self->firstName = $object->firstName;
        $self->lastName = $object->lastName;
        $self->createdAt = Temporal::toDateTime($object->createdAt);
        $self->additionalEmails = json_decode($object->additionalEmails, true);

        return $self;
    }

    /**
     * Convert DTO to Donation
     *
     * @return Donor
     */
    public function toDonor()
    {
        $attributes = get_object_vars($this);

        return new Donor($attributes);
    }
}
