<?php

namespace App\Auth;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/** @implements Arrayable<string, mixed> */
class WindowsUser extends GenericUser implements Arrayable, JsonSerializable
{
    public function __construct(string $principal)
    {
        parent::__construct([
            'id' => 'windows:'.$principal,
            'name' => $principal,
            // Display the authenticated principal; do not invent an AD email address.
            'email' => '',
            'windows_principal' => $principal,
        ]);
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
