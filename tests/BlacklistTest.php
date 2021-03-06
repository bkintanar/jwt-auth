<?php

/*
 * This file is part of jwt-auth.
 *
 * (c) Sean Tymon <tymon148@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tymon\JWTAuth\Test;

use Mockery;
use Tymon\JWTAuth\Payload;
use Tymon\JWTAuth\Blacklist;
use Tymon\JWTAuth\Claims\JwtId;
use Tymon\JWTAuth\Claims\Issuer;
use Tymon\JWTAuth\Claims\Subject;
use Tymon\JWTAuth\Claims\IssuedAt;
use Illuminate\Support\Collection;
use Tymon\JWTAuth\Claims\NotBefore;
use Tymon\JWTAuth\Claims\Expiration;

class BlacklistTest extends AbstractTestCase
{
    /**
     * @var \Mockery\MockInterface
     */
    protected $storage;

    /**
     * @var \Tymon\JWTAuth\Blacklist
     */
    protected $blacklist;

    /**
     * @var \Mockery\MockInterface
     */
    protected $validator;

    public function setUp()
    {
        parent::setUp();

        $this->storage = Mockery::mock('Tymon\JWTAuth\Contracts\Providers\Storage');
        $this->blacklist = new Blacklist($this->storage);

        $this->validator = Mockery::mock('Tymon\JWTAuth\Validators\PayloadValidator');
        $this->validator->shouldReceive('setRefreshFlow->check');
    }

    public function tearDown()
    {
        Mockery::close();

        parent::tearDown();
    }

    /** @test */
    public function it_should_add_a_valid_token_to_the_blacklist()
    {
        $claims = [
            'sub' => new Subject(1),
            'iss' => new Issuer('http://example.com'),
            'exp' => new Expiration($this->testNowTimestamp + 3600),
            'nbf' => new NotBefore($this->testNowTimestamp),
            'iat' => new IssuedAt($this->testNowTimestamp),
            'jti' => new JwtId('foo'),
        ];
        $payload = new Payload(Collection::make($claims), $this->validator);

        $this->storage->shouldReceive('add')->with('foo', ['valid_until' => $this->testNowTimestamp], 20161)->once();
        $this->blacklist->add($payload);
    }

    /** @test */
    public function it_should_add_a_token_with_no_exp_to_the_blacklist_forever()
    {
        $claims = [
            'sub' => new Subject(1),
            'iss' => new Issuer('http://example.com'),
            'nbf' => new NotBefore($this->testNowTimestamp),
            'iat' => new IssuedAt($this->testNowTimestamp),
            'jti' => new JwtId('foo'),
        ];
        $payload = new Payload(Collection::make($claims), $this->validator);

        $this->storage->shouldReceive('forever')->with('foo', 'forever')->once();
        $this->blacklist->add($payload);
    }

    /** @test */
    public function it_should_return_true_when_adding_an_expired_token_to_the_blacklist()
    {
        $claims = [
            'sub' => new Subject(1),
            'iss' => new Issuer('http://example.com'),
            'exp' => new Expiration($this->testNowTimestamp - 3600),
            'nbf' => new NotBefore($this->testNowTimestamp),
            'iat' => new IssuedAt($this->testNowTimestamp),
            'jti' => new JwtId('foo'),
        ];
        $payload = new Payload(Collection::make($claims), $this->validator, true);

        $this->storage->shouldReceive('add')->with('foo', ['valid_until' => $this->testNowTimestamp], 20161)->once();
        $this->assertTrue($this->blacklist->add($payload));
    }

    /** @test */
    public function it_should_check_whether_a_token_has_been_blacklisted()
    {
        $claims = [
            'sub' => new Subject(1),
            'iss' => new Issuer('http://example.com'),
            'exp' => new Expiration($this->testNowTimestamp + 3600),
            'nbf' => new NotBefore($this->testNowTimestamp),
            'iat' => new IssuedAt($this->testNowTimestamp),
            'jti' => new JwtId('foobar'),
        ];
        $payload = new Payload(Collection::make($claims), $this->validator);

        $this->storage->shouldReceive('get')->with('foobar')->once()->andReturn(['valid_until' => $this->testNowTimestamp]);

        $this->assertTrue($this->blacklist->has($payload));
    }

    /** @test */
    public function it_should_check_whether_a_token_has_been_blacklisted_forever()
    {
        $claims = [
            'sub' => new Subject(1),
            'iss' => new Issuer('http://example.com'),
            'exp' => new Expiration($this->testNowTimestamp + 3600),
            'nbf' => new NotBefore($this->testNowTimestamp),
            'iat' => new IssuedAt($this->testNowTimestamp),
            'jti' => new JwtId('foobar'),
        ];
        $payload = new Payload(Collection::make($claims), $this->validator);

        $this->storage->shouldReceive('get')->with('foobar')->once()->andReturn('forever');

        $this->assertTrue($this->blacklist->has($payload));
    }

    /** @test */
    public function it_should_check_whether_a_token_has_been_blacklisted_when_the_token_is_not_blacklisted()
    {
        $claims = [
            'sub' => new Subject(1),
            'iss' => new Issuer('http://example.com'),
            'exp' => new Expiration($this->testNowTimestamp + 3600),
            'nbf' => new NotBefore($this->testNowTimestamp),
            'iat' => new IssuedAt($this->testNowTimestamp),
            'jti' => new JwtId('foobar'),
        ];
        $payload = new Payload(Collection::make($claims), $this->validator);

        $this->storage->shouldReceive('get')->with('foobar')->once()->andReturn(null);

        $this->assertFalse($this->blacklist->has($payload));
    }

    /** @test */
    public function it_should_remove_a_token_from_the_blacklist()
    {
        $claims = [
            'sub' => new Subject(1),
            'iss' => new Issuer('http://example.com'),
            'exp' => new Expiration($this->testNowTimestamp + 3600),
            'nbf' => new NotBefore($this->testNowTimestamp),
            'iat' => new IssuedAt($this->testNowTimestamp),
            'jti' => new JwtId('foobar'),
        ];
        $payload = new Payload(Collection::make($claims), $this->validator);

        $this->storage->shouldReceive('destroy')->with('foobar')->andReturn(true);
        $this->assertTrue($this->blacklist->remove($payload));
    }

    /** @test */
    public function it_should_set_a_custom_unique_key_for_the_blacklist()
    {
        $claims = [
            'sub' => new Subject(1),
            'iss' => new Issuer('http://example.com'),
            'exp' => new Expiration($this->testNowTimestamp + 3600),
            'nbf' => new NotBefore($this->testNowTimestamp),
            'iat' => new IssuedAt($this->testNowTimestamp),
            'jti' => new JwtId('foobar'),
        ];
        $payload = new Payload(Collection::make($claims), $this->validator);

        $this->storage->shouldReceive('get')->with(1)->once()->andReturn(['valid_until' => $this->testNowTimestamp]);

        $this->assertTrue($this->blacklist->setKey('sub')->has($payload));
        $this->assertSame(1, $this->blacklist->getKey($payload));
    }

    /** @test */
    public function it_should_empty_the_blacklist()
    {
        $this->storage->shouldReceive('flush');
        $this->assertTrue($this->blacklist->clear());
    }
}
