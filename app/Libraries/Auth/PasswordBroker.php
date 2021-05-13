<?php

namespace App\Libraries\Auth;

use Closure;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Illuminate\Auth\Passwords\PasswordBroker as OriginalPasswordBroker;

class PasswordBroker extends OriginalPasswordBroker
{

    public function sendResetLink(array $credentials)
    {
        // First we will check to see if we found a user at the given credentials and
        // if we did not we will redirect back to this current URI with a piece of
        // "flash" data in the session to indicate to the developers the errors.

        $credentials['website_id'] = app('config')['wmo_website.website_id'];
        $credentials['status'] = User::STATUS_ACTIVE;
        $credentials['register_via'] = User::RAGISTER_VIA_SITE;
        $user = User::where($credentials)->first();

        if (is_null($user)) {
            return static::INVALID_USER;
        }

        if (method_exists($this->tokens, 'recentlyCreatedToken') &&
            $this->tokens->recentlyCreatedToken($user)) {
            return static::RESET_THROTTLED;
        }

        // Once we have the reset token, we are ready to send the message out to this
        // user with a link to reset their password. We will then redirect back to
        // the current URI having nothing set in the session to indicate errors.

        $protocol = strpos(URL::to('/'), 'http://') !== false ? 'http://' : 'https://';
        config(['app.url' => $protocol . config('wmo_website.base_url')]);
        $user->sendPasswordResetNotification(
            $this->tokens->create($user)
        );

        return static::RESET_LINK_SENT;
    }

    /**
     * Reset the password for the given token.
     *
     * @param  array  $credentials
     * @param  \Closure  $callback
     * @return mixed
     */
    public function reset(array $credentials, Closure $callback)
    {
        $user = $this->validateReset($credentials);

        // If the responses from the validate method is not a user instance, we will
        // assume that it is a redirect and simply return it from this method and
        // the user is properly redirected having an error message on the post.
        if (!$user instanceof User) {
            return $user;
        }

        $password = $credentials['password'];

        // Once the reset has been validated, we'll call the given callback with the
        // new password. This gives the user an opportunity to store the password
        // in their persistent storage. Then we'll delete the token and return.
        $callback($user, $password);

        $this->tokens->deleteExisting($user);

        return static::PASSWORD_RESET;
    }

    /**
     * Validate a password reset for the given credentials.
     *
     * @param  array  $credentials
     * @return \Illuminate\Contracts\Auth\CanResetPassword|string
     */
    protected function validateReset(array $credentials)
    {
        $user = User::where([
            'email' => $credentials['email'],
            'website_id' => app('config')['wmo_website.website_id'],
        ])->first();

        if (is_null($user)) {
            return static::INVALID_USER;
        }

        if (!$this->tokens->exists($user, $credentials['token'])) {
            return static::INVALID_TOKEN;
        }

        return $user;
    }

    

    public function sendResetPasswordToken(array $credentials)
    {
        // First we will check to see if we found a user at the given credentials and
        // if we did not we will redirect back to this current URI with a piece of
        // "flash" data in the session to indicate to the developers the errors.

        $credentials['website_id'] = app('config')['wmo_website.website_id'];
        $user = User::where($credentials)->first();

        if (is_null($user)) {
            return static::INVALID_USER;
        }

        if (method_exists($this->tokens, 'recentlyCreatedToken') &&
            $this->tokens->recentlyCreatedToken($user)) {
            return static::RESET_THROTTLED;
        }

        // Once we have the reset token, we are ready to send the message out to this
        // user with a link to reset their password. We will then redirect back to
        // the current URI having nothing set in the session to indicate errors.

        $protocol = stripos($_SERVER['SERVER_PROTOCOL'], 'https') === 0 ? 'https://' : 'http://';
        config(['app.url' => $protocol . config('wmo_website.base_url')]);    
        

        return $this->tokens->create($user);
    }
    

}
