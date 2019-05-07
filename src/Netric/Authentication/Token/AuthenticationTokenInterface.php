<?php
namespace Netric\Authentication\Token;

interface AuthenticationTokenInterface
{
    /**
     * Check if a token is valid
     *
     * @return bool
     */
    public function tokenIsValid(): bool;

    /**
     * Get GUID for the user of a token
     *
     * @return string
     */
    public function getUserGuid(): string;
}
