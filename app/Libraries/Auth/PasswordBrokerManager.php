<?php

namespace App\Libraries\Auth;

use App\Libraries\Auth\DatabaseTokenRepository;
use App\Libraries\Auth\PasswordBroker;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PasswordBrokerManager
// class PasswordBrokerManager extends OriginalPasswordBrokerManager

{

    public function resolve($name)
    {
        $app = app();

        $config = $this->getConfig($name, $app);

        if (is_null($config)) {
            throw new InvalidArgumentException("Password resetter [{$name}] is not defined.");
        }

        // The password broker uses a token repository to validate tokens and send user
        // password e-mails, as well as validating that password reset process as an
        // aggregate service of sorts providing a convenient interface for resets.
        return new PasswordBroker(
            $this->createTokenRepository($config, $app),
            $app['auth']->createUserProvider($config['provider'] ?? null)
        );
    }

    protected function createTokenRepository(array $config, $app)
    {
        $key = $app['config']['app.key'];

        if (Str::startsWith($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        $connection = $config['connection'] ?? null;

        return new DatabaseTokenRepository(
            $app['db']->connection($connection),
            $app['hash'],
            $config['table'],
            $key,
            $config['expire'],
            $config['throttle'] ?? 0
        );
    }

    protected function getConfig($name, $app)
    {
        return $app['config']["auth.passwords.{$name}"];
    }

    public function getDefaultDriver($app)
    {
        return $app['config']['auth.defaults.passwords'];
    }
}
