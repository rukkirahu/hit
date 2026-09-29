<?php

declare(strict_types=1);

namespace Tests\Unit;

use Hit\Util\Lldap;
use Symfony\Component\Ldap\Adapter\CollectionInterface;
use Symfony\Component\Ldap\Adapter\EntryManagerInterface;
use Symfony\Component\Ldap\Adapter\QueryInterface;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\LdapInterface;

// Helper: create an Lldap instance with a fully mocked LdapInterface.
function makeLldap(string $baseDn = 'ou=people,dc=example,dc=com'): Lldap {
	return new Lldap('ldap://localhost', 'cn=admin', 'secret', $baseDn, test()->createMock(LdapInterface::class));
}

// ---------------------------------------------------------------------------
// Constructor — injected LdapInterface
// ---------------------------------------------------------------------------

test('constructor with injected LdapInterface does not call bind', function () {
	$ldap = test()->createMock(LdapInterface::class);
	$ldap->expects(test()->never())->method('bind');

	new Lldap('ldap://localhost', 'cn=admin', 'secret', 'ou=people,dc=example,dc=com', $ldap);
});

// ---------------------------------------------------------------------------
// getUser
// ---------------------------------------------------------------------------

test('getUser queries with escaped email filter', function () {
	$ldap = test()->createMock(LdapInterface::class);
	$query = test()->createMock(QueryInterface::class);
	$collection = test()->createMock(CollectionInterface::class);

	// The escape method should be called and return the escaped value.
	$ldap->method('escape')->with('test@example.com', '', LdapInterface::ESCAPE_FILTER)->willReturn('test\40example.com');
	$ldap
		->expects(test()->once())
		->method('query')
		->with('ou=people,dc=example,dc=com', '(mail=test\40example.com)', ['filter' => ['mail']])
		->willReturn($query);
	$query->method('execute')->willReturn($collection);

	$lldap = new Lldap('ldap://localhost', 'cn=admin', 'secret', 'ou=people,dc=example,dc=com', $ldap);

	expect($lldap->getUser('test@example.com'))->toBe($collection);
});

test('getUser escapes special filter characters', function () {
	$ldap = test()->createMock(LdapInterface::class);
	$query = test()->createMock(QueryInterface::class);
	$collection = test()->createMock(CollectionInterface::class);

	// Simulate an injection attempt: *)(uid=*))(|(uid=*
	$raw = '*)(uid=*))(|(uid=*';
	$escaped = '\2a\29\28uid\3d\2a\29\29\28|\28uid\3d\2a';

	$ldap->method('escape')->with($raw, '', LdapInterface::ESCAPE_FILTER)->willReturn($escaped);
	$ldap
		->expects(test()->once())
		->method('query')
		->with('dc=example,dc=com', "(mail=$escaped)", ['filter' => ['mail']])
		->willReturn($query);
	$query->method('execute')->willReturn($collection);

	$lldap = new Lldap('ldap://localhost', 'cn=admin', 'secret', 'dc=example,dc=com', $ldap);

	$lldap->getUser($raw);
});

// ---------------------------------------------------------------------------
// getAllUsersForGroup
// ---------------------------------------------------------------------------

test('getAllUsersForGroup queries with escaped group filter', function () {
	$ldap = test()->createMock(LdapInterface::class);
	$query = test()->createMock(QueryInterface::class);
	$collection = test()->createMock(CollectionInterface::class);

	$ldap->method('escape')->with('cn=admins', '', LdapInterface::ESCAPE_FILTER)->willReturn('cn\3dadmins');
	$ldap
		->expects(test()->once())
		->method('query')
		->with('dc=example,dc=com', '(memberOf=cn\3dadmins)', ['filter' => ['mail']])
		->willReturn($query);
	$query->method('execute')->willReturn($collection);

	$lldap = new Lldap('ldap://localhost', 'cn=admin', 'secret', 'dc=example,dc=com', $ldap);

	expect($lldap->getAllUsersForGroup('cn=admins'))->toBe($collection);
});

// ---------------------------------------------------------------------------
// createUser
// ---------------------------------------------------------------------------

test('createUser adds entry with correct DN and attributes', function () {
	$ldap = test()->createMock(LdapInterface::class);
	$entryManager = test()->createMock(EntryManagerInterface::class);

	$ldap->method('getEntryManager')->willReturn($entryManager);

	$capturedEntry = null;
	$entryManager
		->expects(test()->once())
		->method('add')
		->with(test()->callback(function (Entry $entry) use (&$capturedEntry) {
			$capturedEntry = $entry;

			return $entry->getDn() === 'uid=jdoe,ou=people,dc=example,dc=com';
		}))
		->willReturnSelf();

	// update() is called for the password — just expect it.
	$entryManager->expects(test()->once())->method('update');

	$lldap = new Lldap('ldap://localhost', 'cn=admin', 'secret', 'ou=people,dc=example,dc=com', $ldap);

	$result = $lldap->createUser('jdoe@example.com', 'pass123', 'jdoe', 'John', 'John Doe', 'Doe');

	expect($result)->toBeTrue();
	expect($capturedEntry)->not->toBeNull();
	expect($capturedEntry->getDn())->toBe('uid=jdoe,ou=people,dc=example,dc=com');
	expect($capturedEntry->getAttribute('objectClass'))->toBe(['inetOrgPerson', 'organizationalPerson', 'person', 'top']);
	expect($capturedEntry->getAttribute('uid'))->toBe(['jdoe']);
	expect($capturedEntry->getAttribute('mail'))->toBe(['jdoe@example.com']);
	expect($capturedEntry->getAttribute('givenName'))->toBe(['John']);
	expect($capturedEntry->getAttribute('cn'))->toBe(['John Doe']);
	expect($capturedEntry->getAttribute('sn'))->toBe(['Doe']);
});

test('createUser falls back to mail for uid when uid is empty', function () {
	$ldap = test()->createMock(LdapInterface::class);
	$entryManager = test()->createMock(EntryManagerInterface::class);

	$ldap->method('getEntryManager')->willReturn($entryManager);

	$capturedEntry = null;
	$entryManager
		->expects(test()->once())
		->method('add')
		->with(test()->callback(function (Entry $entry) use (&$capturedEntry) {
			$capturedEntry = $entry;

			return true;
		}))
		->willReturnSelf();
	$entryManager->expects(test()->once())->method('update');

	$lldap = new Lldap('ldap://localhost', 'cn=admin', 'secret', 'ou=people,dc=example,dc=com', $ldap);

	$lldap->createUser('jane@example.com', 'pass456');

	// uid defaults to mail when not provided.
	expect($capturedEntry->getDn())->toBe('uid=jane@example.com,ou=people,dc=example,dc=com');
	expect($capturedEntry->getAttribute('uid'))->toBe(['jane@example.com']);
});

test('createUser omits optional attributes when empty', function () {
	$ldap = test()->createMock(LdapInterface::class);
	$entryManager = test()->createMock(EntryManagerInterface::class);

	$ldap->method('getEntryManager')->willReturn($entryManager);

	$capturedEntry = null;
	$entryManager
		->expects(test()->once())
		->method('add')
		->with(test()->callback(function (Entry $entry) use (&$capturedEntry) {
			$capturedEntry = $entry;

			return true;
		}))
		->willReturnSelf();
	$entryManager->expects(test()->once())->method('update');

	$lldap = new Lldap('ldap://localhost', 'cn=admin', 'secret', 'ou=people,dc=example,dc=com', $ldap);

	$lldap->createUser('bob@example.com', 'pass', 'bob');

	expect($capturedEntry->hasAttribute('givenName'))->toBeFalse();
	expect($capturedEntry->hasAttribute('cn'))->toBeFalse();
	expect($capturedEntry->hasAttribute('sn'))->toBeFalse();
});

test('createUser update uses base_dn not hardcoded value', function () {
	$ldap = test()->createMock(LdapInterface::class);
	$entryManager = test()->createMock(EntryManagerInterface::class);

	$ldap->method('getEntryManager')->willReturn($entryManager);

	$capturedUpdateEntry = null;
	$entryManager->method('add')->willReturnSelf();
	$entryManager
		->expects(test()->once())
		->method('update')
		->with(test()->callback(function (Entry $entry) use (&$capturedUpdateEntry) {
			$capturedUpdateEntry = $entry;

			return true;
		}))
		->willReturnSelf();

	$lldap = new Lldap('ldap://localhost', 'cn=admin', 'secret', 'ou=users,dc=myorg,dc=org', $ldap);

	$lldap->createUser('alice@myorg.org', 'secret', 'alice');

	// The update entry DN must use the configured base_dn, not a hardcoded one.
	expect($capturedUpdateEntry->getDn())->toBe('uid=alice,ou=users,dc=myorg,dc=org');
	expect($capturedUpdateEntry->getAttribute('userPassword'))->toBe(['secret']);
});
