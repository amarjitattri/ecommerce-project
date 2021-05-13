<?php

namespace App\Libraries\Auth;

use Carbon\Carbon;
use Illuminate\Auth\Passwords\DatabaseTokenRepository as OriginalDatabaseTokenRepository;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

class DatabaseTokenRepository extends OriginalDatabaseTokenRepository
{

    public function recentlyCreatedToken(CanResetPasswordContract $user)
    {
        $record = (array) $this->getTable()->where(
            'user_id', $user->id
        )->first();

        return $record && $this->tokenRecentlyCreated($record['created_at']);
    }

    public function create(CanResetPasswordContract $user)
    {
        $this->deleteExisting($user);

        // We will create a new, random token for the user so that we can e-mail them
        // a safe link to the password reset form. Then we will insert a record in
        // the database so that we can verify the token within the actual reset.
        $token = $this->createNewToken();

        $this->getTable()->insert($this->getPayload($user, $token));

        return $token;
    }

    /**
     * Delete all existing reset tokens from the database.
     *
     * @param  \Illuminate\Contracts\Auth\CanResetPassword  $user
     * @return int
     */
    public function deleteExisting(CanResetPasswordContract $user)
    {
        return $this->getTable()->where('user_id', $user->id)->delete();
    }

    /**
     * Build the record payload for the table.
     *
     * @param  string  $email
     * @param  string  $token
     * @return array
     */
    protected function getPayload($user, $token)
    {
        return ['user_id' => $user->id, 'token' => $this->hasher->make($token), 'created_at' => new Carbon];
    }

    /**
     * Determine if a token record exists and is valid.
     *
     * @param  \Illuminate\Contracts\Auth\CanResetPassword  $user
     * @param  string  $token
     * @return bool
     */
    public function exists(CanResetPasswordContract $user, $token)
    {
        $record = (array) $this->getTable()->where(
            'user_id', $user->id
        )->first();

        return $record && !$this->tokenExpired($record['created_at']) && $this->hasher->check($token, $record['token']);
    }

}
