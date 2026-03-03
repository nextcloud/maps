<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OCA\DAV\CardDAV;

use OC\Search\Filter\DateTimeFilter;
use OCA\DAV\Connector\Sabre\Principal;
use OCA\DAV\DAV\Sharing\Backend;
use OCA\DAV\DAV\Sharing\IShareable;
use OCA\DAV\Events\AddressBookCreatedEvent;
use OCA\DAV\Events\AddressBookDeletedEvent;
use OCA\DAV\Events\AddressBookShareUpdatedEvent;
use OCA\DAV\Events\AddressBookUpdatedEvent;
use OCA\DAV\Events\CardCreatedEvent;
use OCA\DAV\Events\CardDeletedEvent;
use OCA\DAV\Events\CardMovedEvent;
use OCA\DAV\Events\CardUpdatedEvent;
use OCP\AppFramework\Db\TTransactional;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use OCP\IUserManager;
use PDO;
use Sabre\CardDAV\Backend\BackendInterface;
use Sabre\CardDAV\Backend\SyncSupport;
use Sabre\CardDAV\Plugin;
use Sabre\DAV\Exception\BadRequest;
use Sabre\VObject\Component\VCard;
use Sabre\VObject\Reader;

class CardDavBackend implements BackendInterface, SyncSupport {
	use TTransactional;
	public const PERSONAL_ADDRESSBOOK_URI = 'contacts';
	public const PERSONAL_ADDRESSBOOK_NAME = 'Contacts';

	/** @var array properties to index */
	public static array $indexProperties = [
		'BDAY', 'UID', 'N', 'FN', 'TITLE', 'ROLE', 'NOTE', 'NICKNAME',
		'ORG', 'CATEGORIES', 'EMAIL', 'TEL', 'IMPP', 'ADR', 'URL', 'GEO',
		'CLOUD', 'X-SOCIALPROFILE'];

	/**
	 * @var string[] Map of uid => display name
	 */
	protected array $userDisplayNames;

	public function __construct(
		private IDBConnection $db,
		private Principal $principalBackend,
		private IUserManager $userManager,
		private IEventDispatcher $dispatcher,
		private Sharing\Backend $sharingBackend,
	) {
	}

	/**
	 * Return the number of address books for a principal
	 *
	 * @param $principalUri
	 * @return int
	 */
	public function getAddressBooksForUserCount($principalUri)
 {
 }

	/**
	 * Returns the list of address books for a specific user.
	 *
	 * Every addressbook should have the following properties:
	 *   id - an arbitrary unique id
	 *   uri - the 'basename' part of the url
	 *   principaluri - Same as the passed parameter
	 *
	 * Any additional clark-notation property may be passed besides this. Some
	 * common ones are :
	 *   {DAV:}displayname
	 *   {urn:ietf:params:xml:ns:carddav}addressbook-description
	 *   {http://calendarserver.org/ns/}getctag
	 *
	 * @param string $principalUri
	 * @return array
	 */
	public function getAddressBooksForUser($principalUri)
 {
 }

	public function getUsersOwnAddressBooks($principalUri)
 {
 }

	/**
	 * @param int $addressBookId
	 */
	public function getAddressBookById(int $addressBookId): ?array
 {
 }

	public function getAddressBooksByUri(string $principal, string $addressBookUri): ?array
 {
 }

	/**
	 * Updates properties for an address book.
	 *
	 * The list of mutations is stored in a Sabre\DAV\PropPatch object.
	 * To do the actual updates, you must tell this object which properties
	 * you're going to process with the handle() method.
	 *
	 * Calling the handle method is like telling the PropPatch object "I
	 * promise I can handle updating this property".
	 *
	 * Read the PropPatch documentation for more info and examples.
	 *
	 * @param string $addressBookId
	 * @param \Sabre\DAV\PropPatch $propPatch
	 * @return void
	 */
	public function updateAddressBook($addressBookId, \Sabre\DAV\PropPatch $propPatch)
 {
 }

	/**
	 * Creates a new address book
	 *
	 * @param string $principalUri
	 * @param string $url Just the 'basename' of the url.
	 * @param array $properties
	 * @return int
	 * @throws BadRequest
	 */
	public function createAddressBook($principalUri, $url, array $properties)
 {
 }

	/**
	 * Deletes an entire addressbook and all its contents
	 *
	 * @param mixed $addressBookId
	 * @return void
	 */
	public function deleteAddressBook($addressBookId)
 {
 }

	/**
	 * Returns all cards for a specific addressbook id.
	 *
	 * This method should return the following properties for each card:
	 *   * carddata - raw vcard data
	 *   * uri - Some unique url
	 *   * lastmodified - A unix timestamp
	 *
	 * It's recommended to also return the following properties:
	 *   * etag - A unique etag. This must change every time the card changes.
	 *   * size - The size of the card in bytes.
	 *
	 * If these last two properties are provided, less time will be spent
	 * calculating them. If they are specified, you can also omit carddata.
	 * This may speed up certain requests, especially with large cards.
	 *
	 * @param mixed $addressbookId
	 * @return array
	 */
	public function getCards($addressbookId)
 {
 }

	/**
	 * Returns a specific card.
	 *
	 * The same set of properties must be returned as with getCards. The only
	 * exception is that 'carddata' is absolutely required.
	 *
	 * If the card does not exist, you must return false.
	 *
	 * @param mixed $addressBookId
	 * @param string $cardUri
	 * @return array
	 */
	public function getCard($addressBookId, $cardUri)
 {
 }

	/**
	 * Returns a list of cards.
	 *
	 * This method should work identical to getCard, but instead return all the
	 * cards in the list as an array.
	 *
	 * If the backend supports this, it may allow for some speed-ups.
	 *
	 * @param mixed $addressBookId
	 * @param array $uris
	 * @return array
	 */
	public function getMultipleCards($addressBookId, array $uris)
 {
 }

	/**
	 * Creates a new card.
	 *
	 * The addressbook id will be passed as the first argument. This is the
	 * same id as it is returned from the getAddressBooksForUser method.
	 *
	 * The cardUri is a base uri, and doesn't include the full path. The
	 * cardData argument is the vcard body, and is passed as a string.
	 *
	 * It is possible to return an ETag from this method. This ETag is for the
	 * newly created resource, and must be enclosed with double quotes (that
	 * is, the string itself must contain the double quotes).
	 *
	 * You should only return the ETag if you store the carddata as-is. If a
	 * subsequent GET request on the same card does not have the same body,
	 * byte-by-byte and you did return an ETag here, clients tend to get
	 * confused.
	 *
	 * If you don't return an ETag, you can just return null.
	 *
	 * @param mixed $addressBookId
	 * @param string $cardUri
	 * @param string $cardData
	 * @param bool $checkAlreadyExists
	 * @return string
	 */
	public function createCard($addressBookId, $cardUri, $cardData, bool $checkAlreadyExists = true)
 {
 }

	/**
	 * Updates a card.
	 *
	 * The addressbook id will be passed as the first argument. This is the
	 * same id as it is returned from the getAddressBooksForUser method.
	 *
	 * The cardUri is a base uri, and doesn't include the full path. The
	 * cardData argument is the vcard body, and is passed as a string.
	 *
	 * It is possible to return an ETag from this method. This ETag should
	 * match that of the updated resource, and must be enclosed with double
	 * quotes (that is: the string itself must contain the actual quotes).
	 *
	 * You should only return the ETag if you store the carddata as-is. If a
	 * subsequent GET request on the same card does not have the same body,
	 * byte-by-byte and you did return an ETag here, clients tend to get
	 * confused.
	 *
	 * If you don't return an ETag, you can just return null.
	 *
	 * @param mixed $addressBookId
	 * @param string $cardUri
	 * @param string $cardData
	 * @return string
	 */
	public function updateCard($addressBookId, $cardUri, $cardData)
 {
 }

	/**
	 * @throws Exception
	 */
	public function moveCard(int $sourceAddressBookId, int $targetAddressBookId, string $cardUri, string $oldPrincipalUri): bool
 {
 }

	/**
	 * Deletes a card
	 *
	 * @param mixed $addressBookId
	 * @param string $cardUri
	 * @return bool
	 */
	public function deleteCard($addressBookId, $cardUri)
 {
 }

	/**
	 * The getChanges method returns all the changes that have happened, since
	 * the specified syncToken in the specified address book.
	 *
	 * This function should return an array, such as the following:
	 *
	 * [
	 *   'syncToken' => 'The current synctoken',
	 *   'added'   => [
	 *      'new.txt',
	 *   ],
	 *   'modified'   => [
	 *      'modified.txt',
	 *   ],
	 *   'deleted' => [
	 *      'foo.php.bak',
	 *      'old.txt'
	 *   ]
	 * ];
	 *
	 * The returned syncToken property should reflect the *current* syncToken
	 * of the calendar, as reported in the {http://sabredav.org/ns}sync-token
	 * property. This is needed here too, to ensure the operation is atomic.
	 *
	 * If the $syncToken argument is specified as null, this is an initial
	 * sync, and all members should be reported.
	 *
	 * The modified property is an array of nodenames that have changed since
	 * the last token.
	 *
	 * The deleted property is an array with nodenames, that have been deleted
	 * from collection.
	 *
	 * The $syncLevel argument is basically the 'depth' of the report. If it's
	 * 1, you only have to report changes that happened only directly in
	 * immediate descendants. If it's 2, it should also include changes from
	 * the nodes below the child collections. (grandchildren)
	 *
	 * The $limit argument allows a client to specify how many results should
	 * be returned at most. If the limit is not specified, it should be treated
	 * as infinite.
	 *
	 * If the limit (infinite or not) is higher than you're willing to return,
	 * you should throw a Sabre\DAV\Exception\TooMuchMatches() exception.
	 *
	 * If the syncToken is expired (due to data cleanup) or unknown, you must
	 * return null.
	 *
	 * The limit is 'suggestive'. You are free to ignore it.
	 *
	 * @param string $addressBookId
	 * @param string $syncToken
	 * @param int $syncLevel
	 * @param int|null $limit
	 * @return array
	 */
	public function getChangesForAddressBook($addressBookId, $syncToken, $syncLevel, $limit = null)
 {
 }

	/**
	 * Adds a change record to the addressbookchanges table.
	 *
	 * @param mixed $addressBookId
	 * @param string $objectUri
	 * @param int $operation 1 = add, 2 = modify, 3 = delete
	 * @return void
	 */
	protected function addChange(int $addressBookId, string $objectUri, int $operation): void
 {
 }

	/**
	 * @param list<array{href: string, commonName: string, readOnly: bool}> $add
	 * @param list<string> $remove
	 */
	public function updateShares(IShareable $shareable, array $add, array $remove): void
 {
 }

	/**
	 * Search contacts in a specific address-book
	 *
	 * @param int $addressBookId
	 * @param string $pattern which should match within the $searchProperties
	 * @param array $searchProperties defines the properties within the query pattern should match
	 * @param array $options = array() to define the search behavior
	 *                       - 'types' boolean (since 15.0.0) If set to true, fields that come with a TYPE property will be an array
	 *                       - 'escape_like_param' - If set to false wildcards _ and % are not escaped, otherwise they are
	 *                       - 'limit' - Set a numeric limit for the search results
	 *                       - 'offset' - Set the offset for the limited search results
	 *                       - 'wildcard' - Whether the search should use wildcards
	 * @psalm-param array{types?: bool, escape_like_param?: bool, limit?: int, offset?: int, wildcard?: bool} $options
	 * @return array an array of contacts which are arrays of key-value-pairs
	 */
	public function search($addressBookId, $pattern, $searchProperties, $options = []): array
 {
 }

	/**
	 * Search contacts in all address-books accessible by a user
	 *
	 * @param string $principalUri
	 * @param string $pattern
	 * @param array $searchProperties
	 * @param array $options
	 * @return array
	 */
	public function searchPrincipalUri(string $principalUri, string $pattern, array $searchProperties, array $options = []): array
 {
 }

	/**
	 * @param int $bookId
	 * @param string $name
	 * @return array
	 */
	public function collectCardProperties($bookId, $name)
 {
 }

	/**
	 * get URI from a given contact
	 *
	 * @param int $id
	 * @return string
	 */
	public function getCardUri($id)
 {
 }

	/**
	 * return contact with the given URI
	 *
	 * @param int $addressBookId
	 * @param string $uri
	 * @returns array
	 */
	public function getContact($addressBookId, $uri)
 {
 }

	/**
	 * Returns the list of people whom this address book is shared with.
	 *
	 * Every element in this array should have the following properties:
	 *   * href - Often a mailto: address
	 *   * commonName - Optional, for example a first + last name
	 *   * status - See the Sabre\CalDAV\SharingPlugin::STATUS_ constants.
	 *   * readOnly - boolean
	 *
	 * @return list<array{href: string, commonName: string, status: int, readOnly: bool, '{http://owncloud.org/ns}principal': string, '{http://owncloud.org/ns}group-share': bool}>
	 */
	public function getShares(int $addressBookId): array
 {
 }

	/**
	 * update properties table
	 *
	 * @param int $addressBookId
	 * @param string $cardUri
	 * @param string $vCardSerialized
	 */
	protected function updateProperties($addressBookId, $cardUri, $vCardSerialized)
 {
 }

	/**
	 * read vCard data into a vCard object
	 *
	 * @param string $cardData
	 * @return VCard
	 */
	protected function readCard($cardData)
 {
 }

	/**
	 * delete all properties from a given card
	 *
	 * @param int $addressBookId
	 * @param int $cardId
	 */
	protected function purgeProperties($addressBookId, $cardId)
 {
 }

	/**
	 * Get ID from a given contact
	 */
	protected function getCardId(int $addressBookId, string $uri): int
 {
 }

	/**
	 * For shared address books the sharee is set in the ACL of the address book
	 *
	 * @param int $addressBookId
	 * @param list<array{privilege: string, principal: string, protected: bool}> $acl
	 * @return list<array{privilege: string, principal: string, protected: bool}>
	 */
	public function applyShareAcl(int $addressBookId, array $acl): array
 {
 }

	/**
	 * @throws \InvalidArgumentException
	 */
	public function pruneOutdatedSyncTokens(int $keep, int $retention): int
 {
 }
}
