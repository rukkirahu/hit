<?php

declare(strict_types=1);

namespace Hit\Util;

use Symfony\Component\Ldap\Adapter\CollectionInterface;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Ldap;
use Symfony\Component\Ldap\LdapInterface;

class Lldap {
	private LdapInterface $Ldap;

	public function __construct(
		private string $uri,
		private string $username,
		#[\SensitiveParameter]
		private string $password,
		private string $base_dn,
		?LdapInterface $ldap = null,
	) {
		if ($ldap !== null) {
			$this->Ldap = $ldap;

			return;
		}

		$this->Ldap = Ldap::create('ext_ldap', ['connection_string' => $uri]);
		$this->Ldap->bind($username . ',' . $base_dn, $password);
	}

	public function getUser(string $email): CollectionInterface {
		$escaped = $this->Ldap->escape($email, '', LdapInterface::ESCAPE_FILTER);
		$query = $this->Ldap->query($this->base_dn, "(mail={$escaped})", ['filter' => ['mail']]);

		return $query->execute();
	}

	public function createUser(
		string $mail,
		#[\SensitiveParameter]
		string $password,
		string $uid = '',
		string $givenName = '',
		string $cn = '',
		string $sn = '',
	): bool {
		$uid = $uid ?: $mail;
		$attributes = [
			'objectClass' => ['inetOrgPerson', 'organizationalPerson', 'person', 'top'],
			'uid' => [$uid],
			'mail' => [$mail],
		];

		if ($givenName) {
			$attributes['givenName'] = [$givenName];
		}
		if ($cn) {
			$attributes['cn'] = [$cn];
		}
		if ($sn) {
			$attributes['sn'] = [$sn];
		}

		$entry = new Entry("uid={$uid}," . $this->base_dn, $attributes);
		$this->Ldap->getEntryManager()->add($entry);

		$entry = new Entry("uid={$uid}," . $this->base_dn, ['userPassword' => [$password]]);

		$this->Ldap->getEntryManager()->update($entry);

		return true;
	}

	public function getAllUsersForGroup(string $group): CollectionInterface {
		$escaped = $this->Ldap->escape($group, '', LdapInterface::ESCAPE_FILTER);
		$query = $this->Ldap->query($this->base_dn, "(memberOf={$escaped})", ['filter' => ['mail']]);

		return $query->execute();
	}
}
