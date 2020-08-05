<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInita06df693e5b7b0635862ee83a52d1612
{
    public static $prefixLengthsPsr4 = array (
        'P' => 
        array (
            'PHPMailer\\PHPMailer\\' => 20,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'PHPMailer\\PHPMailer\\' => 
        array (
            0 => __DIR__ . '/..' . '/phpmailer/phpmailer/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInita06df693e5b7b0635862ee83a52d1612::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInita06df693e5b7b0635862ee83a52d1612::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}