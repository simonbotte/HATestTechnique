<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Controller\Admin;

use App\Entity\User;
use App\Repository\PostRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional test for the controllers defined inside the BlogController used
 * for managing the blog in the backend.
 *
 * See https://symfony.com/doc/current/testing.html#functional-tests
 *
 * Whenever you test resources protected by a firewall, consider using the
 * technique explained in:
 * https://symfony.com/doc/current/testing/http_authentication.html
 *
 * Execute the application tests using this command (requires PHPUnit to be installed):
 *
 *     $ cd your-symfony-project/
 *     $ ./vendor/bin/phpunit
 */
class UserControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();

        /** @var UserRepository $userRepository */
        $userRepository = $this->client->getContainer()->get(UserRepository::class);
        /** @var User $user */
        $user = $userRepository->findOneByUsername('jane_admin');
        $this->client->loginUser($user);
    }

    /**
     * @dataProvider getUrlsForRegularUsers
     */
    public function testAccessDeniedForRegularUsers(string $httpMethod, string $url): void
    {
        $this->client->getCookieJar()->clear();

        /** @var UserRepository $userRepository */
        $userRepository = $this->client->getContainer()->get(UserRepository::class);
        /** @var User $user */
        $user = $userRepository->findOneByUsername('john_user');
        $this->client->loginUser($user);

        $this->client->request($httpMethod, $url);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function getUrlsForRegularUsers(): \Generator
    {
        yield ['GET', '/en/admin/user/'];
        yield ['GET', '/en/admin/user/new'];
    }

    public function testAdminBackendHomePage(): void
    {
        $this->client->request('GET', '/en/admin/user/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists(
            'body#admin_user_index #main tbody tr',
            'The backend homepage displays all the available users.'
        );
    }

    public function testAdminBackendNewUser(): void
    {
        $username = 'username';
        $fullName = 'John Doe';
        $email = '9vQpX@example.com';
        $password = 'p4Ssw0rd';
        

        $this->client->request('GET', '/en/admin/user/new');
        $this->client->submitForm('Create user', [
            'user[username]' => $username,
            'user[fullName]' => $fullName,
            'user[email]' => $email,
            'user[password][first]' => $password,
            'user[password][second]' => $password,
        ]);

        $this->assertResponseRedirects('/en/admin/user/');

        $userRepository = $this->client->getContainer()->get(UserRepository::class);
        $users = $userRepository->findBy(['username' => $username]);

        $this->assertCount(1, $users);
        $this->assertSame($fullName, $users[0]->getFullName());
        $this->assertSame($email, $users[0]->getEmail());
        $this->assertTrue(password_verify($password, $users[0]->getPassword()));
    }
}
