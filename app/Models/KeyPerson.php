<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A person named on the Sponsor Management System: Authorising Officer, Key Contact or Level 1 User. */
class KeyPerson extends Model
{
    protected $table = 'key_personnel';

    public const ROLES = [
        'authorising_officer' => 'Authorising Officer',
        'key_contact' => 'Key Contact',
        'level1_user' => 'Level 1 User',
    ];

    /** Roles held by exactly one person; Level 1 Users can be several. */
    public const SINGLE_ROLES = ['authorising_officer', 'key_contact'];

    protected $fillable = ['business_id', 'role', 'name', 'email', 'phone'];

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }
}
