<?php

namespace App\Console\Commands;

use App\Enums\UserStatus;
use App\Models\User;
use App\Support\PhoneNumber;
use App\Support\Roles;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;

#[Signature('dawat:make-admin {phone : Mobile number, e.g. 017XXXXXXXX} {email : Email used to sign in to /admin}')]
#[Description('Create an admin (or promote an existing user) who can sign in to /admin')]
class MakeAdmin extends Command
{
    public function handle(): int
    {
        $phone = PhoneNumber::normalize($this->argument('phone'));
        if (! $phone) {
            $this->error('Invalid Bangladeshi mobile number.');

            return self::FAILURE;
        }

        $email = strtolower(trim($this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email address.');

            return self::FAILURE;
        }

        $secret = password(label: 'Password for /admin (min 12 characters)', required: true, validate: fn (string $v) => strlen($v) < 12 ? 'Use at least 12 characters.' : null);

        $user = User::updateOrCreate(['phone' => $phone], [
            'email' => $email,
            'password' => $secret,
            'status' => UserStatus::Active,
        ]);
        $user->forceFill(['email_verified_at' => now(), 'phone_verified_at' => $user->phone_verified_at ?? now()])->save();
        $user->assignRole(Roles::ADMIN);

        $this->info("Admin ready: {$email} ({$phone}). Sign in at ".url('/admin'));

        return self::SUCCESS;
    }
}
