<?php

namespace HRC\NectaPay\Contracts;

interface OwnerInterface
{
    /**
     * Get the owner's unique ID (typically UUID).
     */
    public function getId(): string;

    /**
     * Get the owner's display name.
     */
    public function getName(): string;
}
