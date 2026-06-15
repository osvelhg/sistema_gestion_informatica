<?php

namespace App\Ldap;

use LdapRecord\Models\ActiveDirectory\User as LdapUser;

/**
 * Modelo LdapRecord para usuarios de Active Directory.
 * Usa la conexión 'default' registrada en runtime desde LdapSetting.
 */
class User extends LdapUser
{
    public static string $connection = 'default';
}
