<?php
declare( strict_types=1 );

use Tmac\Translator;
use Tmac\ApplicationContext;
use \Tmac\Contract\ConfigInterface;

if ( !function_exists( 'trans' ) ) {
    function trans( string $name = null, array $vars = [], ?string $locale = '' )
    {
        /** @var Translator $translator */
        $translator = ApplicationContext::getContainer()->getShared( Translator::class );
        return $translator->get( $name, $vars, $locale );
    }
}

if ( !function_exists( 'config' ) ) {
    function config( string $name = null, $default = null )
    {
        /** @var ConfigInterface $config */
        $config = ApplicationContext::getContainer()[ 'config' ];
        return $config->get( $name, $default );
    }
}