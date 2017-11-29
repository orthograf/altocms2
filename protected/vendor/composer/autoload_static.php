<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitca937ff263369c3ad4783eb52830add8
{
    public static $files = array (
        '2a404a48a0596622a2af787408f2be5e' => __DIR__ . '/..' . '/xxtea/xxtea/xxtea.php',
        'f084d01b0a599f67676cffef638aa95b' => __DIR__ . '/..' . '/smarty/smarty/libs/bootstrap.php',
    );

    public static $prefixLengthsPsr4 = array (
        'a' => 
        array (
            'avadim\\Chrono\\' => 14,
        ),
        'Z' => 
        array (
            'Zend\\Diactoros\\' => 15,
        ),
        'W' => 
        array (
            'Wikimedia\\Composer\\' => 19,
        ),
        'P' => 
        array (
            'Psr\\Log\\' => 8,
            'Psr\\Http\\Message\\' => 17,
            'PHPMailer\\PHPMailer\\' => 20,
        ),
        'N' => 
        array (
            'Noodlehaus\\' => 11,
        ),
        'A' => 
        array (
            'Aura\\Router\\' => 12,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'avadim\\Chrono\\' => 
        array (
            0 => __DIR__ . '/..' . '/avadim/chrono/src',
        ),
        'Zend\\Diactoros\\' => 
        array (
            0 => __DIR__ . '/..' . '/zendframework/zend-diactoros/src',
        ),
        'Wikimedia\\Composer\\' => 
        array (
            0 => __DIR__ . '/..' . '/wikimedia/composer-merge-plugin/src',
        ),
        'Psr\\Log\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/log/Psr/Log',
        ),
        'Psr\\Http\\Message\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/http-message/src',
        ),
        'PHPMailer\\PHPMailer\\' => 
        array (
            0 => __DIR__ . '/..' . '/phpmailer/phpmailer/src',
        ),
        'Noodlehaus\\' => 
        array (
            0 => __DIR__ . '/..' . '/hassankhan/config/src',
        ),
        'Aura\\Router\\' => 
        array (
            0 => __DIR__ . '/..' . '/aura/router/src',
        ),
    );

    public static $prefixesPsr0 = array (
        'N' => 
        array (
            'NilPortugues' => 
            array (
                0 => __DIR__ . '/..' . '/nilportugues/sphinx-search/src',
            ),
        ),
        'J' => 
        array (
            'JShrink' => 
            array (
                0 => __DIR__ . '/..' . '/tedivm/jshrink/src',
            ),
        ),
    );

    public static $classMap = array (
        'Qevix' => __DIR__ . '/..' . '/qevix/qevix/qevix.php',
        'Text_Diff_Renderer_parallel' => __DIR__ . '/..' . '/cerdic/css-tidy/testing/unit-tests/class.Text_Diff_Renderer_parallel.php',
        'csstidy' => __DIR__ . '/..' . '/cerdic/css-tidy/class.csstidy.php',
        'csstidy_csst' => __DIR__ . '/..' . '/cerdic/css-tidy/testing/unit-tests/class.csstidy_csst.php',
        'csstidy_harness' => __DIR__ . '/..' . '/cerdic/css-tidy/testing/unit-tests/class.csstidy_harness.php',
        'csstidy_optimise' => __DIR__ . '/..' . '/cerdic/css-tidy/class.csstidy_optimise.php',
        'csstidy_print' => __DIR__ . '/..' . '/cerdic/css-tidy/class.csstidy_print.php',
        'csstidy_reporter' => __DIR__ . '/..' . '/cerdic/css-tidy/testing/unit-tests/class.csstidy_reporter.php',
        'csstidy_test_csst' => __DIR__ . '/..' . '/cerdic/css-tidy/testing/unit-tests/test.csst.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitca937ff263369c3ad4783eb52830add8::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitca937ff263369c3ad4783eb52830add8::$prefixDirsPsr4;
            $loader->prefixesPsr0 = ComposerStaticInitca937ff263369c3ad4783eb52830add8::$prefixesPsr0;
            $loader->classMap = ComposerStaticInitca937ff263369c3ad4783eb52830add8::$classMap;

        }, null, ClassLoader::class);
    }
}
