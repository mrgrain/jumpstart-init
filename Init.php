<?php
namespace Jumpstart;

use Composer\Script\Event;
use Composer\Json\JsonFile;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

/**
 * Class Init
 * @package Jumpstart
 */
class Init
{

    protected static $filePattern = '/^.+\.php$/';

    /**
     * Publish th plugin to the WordPress plugin repository
     * @param Event $event
     */
    public static function jumpstart(Event $event)
    {
        // get information
        $info = static::questionnaire($event);

        // update file headers
        $files =  new RegexIterator(
            new RecursiveIteratorIterator(new RecursiveDirectoryIterator('src')),
            static::$filePattern,
            RegexIterator::GET_MATCH
        );
        static::updateFiles($event, $files, $info);

        // create composer.json
        static::mergeConfig($event, $info);
    }

    /**
     * Merge the current config with the updated data
     * @param Event $event
     * @param array $info
     */
    public static function mergeConfig(Event $event, array $info)
    {
        // start
        $io = $event->getIO();

        $options = array(
            "name" => $info['package'],
            "description" => $info['description'],
            "minimum-stability" => "dev",
            "license" => $info['license'],
            "keywords" => array(),
            "type" => "wordpress-plugin",
            "authors" => array(
                array(
                    "name" => $info['author'],
                    "email" => $info['author_email'],
                    "homepage" => $info['author_url'],
                )
            ),
            "scripts" => array(
                "jumpstart" => "Jumpstart\\Init::jumpstart",
                "publish" => "Jumpstart\\Deploy::publish"
            ),
            "require" => array(
                "composer/installers" => "~1.0",
                "mrgrain/jumpstart-trampoline" => "~1.0"
            ),
            "require-dev" => array(
                "mrgrain/jumpstart-init" => "@stable",
                "mrgrain/jumpstart-deploy" => "@stable"
            ),
            "autoload" => array (
                "psr-4" => array(
                    $info['namespace'] => "src/"
                )
            )
        );

        $file = new JsonFile('composer.json');
        $json = $file->encode($options);
        $writeFile = true;

        $io->write('Jumpstart has generated a composer.json for you.');
        if ($io->askConfirmation('Do you want to check it before writing to disk? [yes]'."\t")) {
            $writeFile = false;
            $io->write($json);
            if ($io->askConfirmation('Write the generated composer.json to disk? [yes]'."\t")) {
                $writeFile = true;
            }
        }
        if ($writeFile) {
            $file->write($options);
        }
    }


    /**
     * Update the boilerplate files with the additional information
     * @param Event $event
     * @param $files
     * @param array $info
     */
    public static function updateFiles(Event $event, $files, array $info)
    {
        // start
        $io = $event->getIO();
        if ($io->askConfirmation('Jumpstart will now update your files with the provided information. Okay? [yes]:'."\t")) {
            foreach ($files as $file) {
                $file = array_pop($file);
                $io->write("\t".$file."\t", false);
                $io->write(static::updateFile($file, $info) ? 'OK' : 'Error');
            }
            if (file_exists('plugin.php')) {
                rename('plugin.php', $info['plugin_slug'].'.php');
            }
            $io->write('');
        }
    }

    public static function updateFile($file, array $info)
    {
        $content = file_get_contents($file);

        // headers
        foreach ($info as $key => $value) {
            $content = str_replace("{{".$key."}}", $value, $content, $num);
        }
        // namespace
        $content = str_replace('namespace Vendor\Plugin', 'namespace ' . $info['namespace'], $content);

        return (false !== file_put_contents($file, $content));
    }

    /**
     * The plugin data storage
     */
    protected static $package;
    protected static $namespace;
    protected static $plugin_name;
    protected static $plugin_slug;
    protected static $version;
    protected static $description;
    protected static $url;
    protected static $author;
    protected static $author_email;
    protected static $author_url;
    protected static $license;

    /**
     * Collect information about the plugin
     * @param Event $event
     * @return array
     */
    public static function questionnaire(Event $event)
    {
        // start
        $io = $event->getIO();
        $io->write(array(
            PHP_EOL.PHP_EOL,
            'Welcome to the Jumpstart Plugin generator',
            PHP_EOL.PHP_EOL.PHP_EOL,
            'This command will guide you through creating your basic plugin setup.'
        ));

        // Collect data
        static::$package = $io->askAndValidate(
            'Package (<vendor>/<name>):'."\t",
            'Jumpstart\\Init::validatePackage'
        );
        static::$namespace = $io->askAndValidate(
            'PHP namespace (<vendor>\<name>) ['.static::suggestNamespace(static::$package).']:'."\t",
            'Jumpstart\\Init::validateNamespace',
            null,
            static::suggestNamespace(static::$package)
        );
        static::$plugin_name = $io->ask(
            'Plugin Name ['.static::suggestPluginName(static::$package).']:'."\t",
            static::suggestPluginName(static::$package)
        );
        static::$plugin_slug = $io->askAndValidate(
            'Plugin Slug ['.static::suggestPluginSlug(static::$plugin_name).']:'."\t",
            'Jumpstart\\Init::validateSlug',
            null,
            static::suggestPluginSlug(static::$plugin_name)
        );
        static::$version = $io->askAndValidate(
            'Version (x.x.x) [1.0.0]:'."\t",
            'Jumpstart\\Init::validateVersion',
            null,
            '1.0.0'
        );
        static::$description = $io->askAndValidate(
            'Description []:'."\t",
            'Jumpstart\\Init::validateDescription',
            null,
            ''
        );
        static::$url = $io->askAndValidate(
            'URL []:'."\t",
            'Jumpstart\\Init::validateURL'
        );
        static::$author = $io->ask(
            'Author ['.static::suggestAuthor().']:'."\t",
            static::suggestAuthor()
        );
        static::$author_email = $io->ask(
            'Author Email ['.static::suggestAuthorEmail().']:'."\t",
            static::suggestAuthorEmail()
        );
        static::$author_url = $io->askAndValidate(
            'Author URL []:'."\t",
            'Jumpstart\\Init::validateURL'
        );
        static::$license = $io->ask(
            'License ['.static::suggestLicense().']:'."\t",
            static::suggestLicense()
        );

        return array(
            'package' => static::$package,
            'namespace' => static::$namespace,
            'plugin_name' => static::$plugin_name,
            'plugin_slug' => static::$plugin_slug,
            'version' => static::$version,
            'description' => static::$description,
            'url' => static::$url,
            'author' => static::$author,
            'author_email' => static::$author_email,
            'author_url' => static::$author_url,
            'license' => static::$license,
        );
    }

    public static function validateNotEmpty($value)
    {
        if (strlen(trim($value)) <= 0) {
            throw new \Exception('The value must not be empty.');
        }
        return $value;
    }
    public static function validatePackage($value)
    {
        if (1 !== preg_match("~^[a-z0-9_.-]+/[a-z0-9_.-]+$~", $value)) {
            throw new \Exception('Package name must only be in lowercase and have a vendor name, a forward slash, and a package name, matching: [a-z0-9_.-]+/[a-z0-9_.-]+');
        }
        return $value;
    }
    public static function validateNamespace($value)
    {
        if (1 !== preg_match("~^[a-zA-Z0-9-_]+\\\\[a-zA-Z0-9-_]+$~", $value)) {
            throw new \Exception('Namespace must have a vendor name, a backward slash, and a package name, matching: [a-zA-Z0-9_-]+/[a-zA-Z0-9_-]+');
        }
        return $value;
    }
    public static function validateSlug($value)
    {
        if (1 !== preg_match("~^[a-z0-9-_]+$~", $value)) {
            throw new \Exception('Slug must must only be in lowercase, matching: [a-z0-9-_]+');
        }
        return $value;
    }
    public static function validateVersion($value)
    {
        if (1 !== preg_match("~^[0-9]+\\.[0-9]+\\.[0-9]+(-[a-z0-9-_]+)?$~", $value)) {
            throw new \Exception('Version must only be in the format <x.x.x>, possible followed by a hyphen and a pre-release suffix, e.g. 1.0.0-alpha');
        }
        return $value;
    }
    public static function validateDescription($value)
    {
        if (strlen($value) > 150) {
            throw new \Exception('Description must only have 150 characters');
        }
        return $value;
    }
    public static function validateURL($value)
    {
        if (empty($value)) {
            return '';
        }
        if (false === strpos($value, 'http://') && false === strpos($value, 'https://')) {
            throw new \Exception('Please enter a valid URL');
        }
        return $value;
    }

    public static function suggestNamespace($value)
    {
        $vendor = strtok($value, "/");
        $package = strtok("/");
        return ucfirst($vendor)."\\".ucfirst($package);
    }

    public static function suggestPluginName($value)
    {
        return ucfirst(substr($value, strpos($value, "/")+1));
    }
    public static function suggestPluginSlug($value)
    {
        return strtolower(preg_replace("/[^A-Za-z0-9_]/", "", str_replace(" ", "_", $value)));
    }
    public static function suggestAuthor()
    {
        $author = `git config --global user.name`;
        if (!isset($author) || false === $author || empty($author)) {
            return '';
        }
        return trim($author);
    }
    public static function suggestAuthorEmail()
    {
        $email = `git config --global user.email`;
        if (!isset($email) || false === $email || empty($email)) {
            return '';
        }
        return trim($email);
    }
    public static function suggestLicense()
    {
        return 'GPL-3.0+';
    }
}
