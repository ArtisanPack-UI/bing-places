<?php

declare( strict_types=1 );

use ArtisanPackUI\BingPlaces\Contracts\TokenProvider;

test( 'TokenProvider interface exists', function (): void {
    expect( interface_exists( TokenProvider::class ) )->toBeTrue();
} );

test( 'TokenProvider declares a parameterless accessToken() method returning string', function (): void {
    $reflection = new ReflectionClass( TokenProvider::class );

    expect( $reflection->hasMethod( 'accessToken' ) )->toBeTrue();

    $method = $reflection->getMethod( 'accessToken' );

    expect( $method->isPublic() )->toBeTrue();
    expect( $method->getNumberOfParameters() )->toBe( 0 );

    $returnType = $method->getReturnType();

    expect( $returnType )->not->toBeNull();
    expect( $returnType )->toBeInstanceOf( ReflectionNamedType::class );
    expect( $returnType->getName() )->toBe( 'string' );
    expect( $returnType->allowsNull() )->toBeFalse();
} );

test( 'a class implementing TokenProvider satisfies the contract', function (): void {
    $provider = new class implements TokenProvider {
        /**
         * Return a fixed test access token for contract-satisfaction checks.
         *
         * @since 1.0.0
         *
         * @return string A non-empty OAuth access token.
         */
        public function accessToken(): string
        {
            return 'test-access-token';
        }
    };

    expect( $provider )->toBeInstanceOf( TokenProvider::class );
    expect( $provider->accessToken() )->toBe( 'test-access-token' );
} );
