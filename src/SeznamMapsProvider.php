<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Geocoder\Provider\SeznamMaps;

use Geocoder\Exception\NoResult as GeocoderNoResultException;
use SimpleXMLElement;

class SeznamMapsProvider extends \Geocoder\Provider\AbstractHttpProvider implements \Geocoder\Provider\Provider
{

	/** @internal Seznam Geocode API url */
	const GEOCODE_URI = 'https://api4.mapy.cz/geocode';
	const REVERSE_URI = 'https://api4.mapy.cz/rgeocode';

	// regexes for parsing address
	const RE_STREET = '(?:(?:\sulice\s)?(?P<streetName>(?:[0-9]+(?=[^/,]+))?[^/,0-9]+(?<![\s\,])))';
	const RE_NUMBER = '(?:(?<!č\.p\.)(?:č\.p\.\s+)?(?P<streetNumber>[0-9]+(?:\/[0-9]+)?[a-z]?))';
	// const RE_QUARTER = '(?:\s?čtvrť\s+(?P<quarter>[^,]+))';
	// const RE_CITY = '(?:(?:\s?obec\s)?(?P<city>(?:(?P<city_name>[^,-]+(?<!\s))(?:(?<!\s)-(?!\s)(?P<city_part>\\city_name[^,]+(?<!\s)))?)|(?:[^,]+(?<!\s))))';
	const RE_POSTAL_CODE = '(?P<postalCode>\d{3}\s?\d{2})';
	const RE_DISTRICT = '(?:\s?okres\s+(?P<district>[^,]+))';
	const RE_REGION = '(?:\s?kraj\s+(?P<region>[^,]+))';
	const RE_COUNTRY = '(?P<country>(?:Česká Republika|Slovenská Republika|Slovensko|Česko))';

/**
 * {@inheritDoc}
 */
	public function geocode($address)
	{
		if (filter_var($address, FILTER_VALIDATE_IP)) { // This API doesn't handle IPs
			throw new \Kdyby\Geocoder\Provider\SeznamMaps\UnsupportedOperationException('The SeznamMapsProvider does not support IP addresses');
		}

		$results = [];
		$xml = $this->executeQuery(self::GEOCODE_URI, $query = ['query' => $address]);

		/** @var \SimpleXMLElement $point */
		$point = $xml->point;
		/** @var \SimpleXMLElement $item */
		foreach ($point->children() as $item) {
			if (count($results) === $this->getLimit()) {
				break;
			}

			/** @var \SimpleXMLElement $attrs */
			$attrs = $item->attributes();
			if (in_array((string) $attrs->source, ['area', 'firm'], TRUE)) {
				continue; // ignore
			}

			try {
				$rgeocode = $this->executeQuery(self::REVERSE_URI, ['lat' => (string) $attrs->y, 'lon' => (string) $attrs->x]);

			} catch (GeocoderNoResultException $e) {
				continue; // ignore
			}

			$reversedAddress = $this->findReversedComponent($rgeocode, (string) $attrs->source);
			if ($reversedAddress === NULL || ((string) $reversedAddress->id) !== ((string) $attrs->id)) {
				continue; // not found :(
			}

			$resultSet = $this->reversedToResult($rgeocode, (string) $attrs->source);
			if (empty($resultSet['latitude']) || empty($resultSet['longitude'])) {
				$resultSet['latitude'] = (string) $reversedAddress->y;
				$resultSet['longitude'] = (string) $reversedAddress->x;
			}

			$results[] = $resultSet;
		}

		return $this->returnResults($results);
	}

	/**
	 * @param \SimpleXMLElement $rgeocode
	 * @return \SimpleXMLElement|NULL
	 */
	private function findReversedComponent(SimpleXMLElement $rgeocode, $type)
	{
		foreach ($rgeocode->children() as $item) {
			/** @var \SimpleXMLElement $item */
			$attrs = $item->attributes();
			/** @var \SimpleXMLElement $attrs */
			if ((string) $attrs->type === $type) {
				return $attrs;
			}
		}

		return NULL;
	}

	/**
	 * {@inheritdoc}
	 */
	public function reverse($latitude, $longitude)
	{
		$xml = $this->executeQuery(self::REVERSE_URI, $query = ['lat' => $latitude, 'lon' => $longitude]);

		$resultSet = $this->reversedToResult($xml);

		if (empty($resultSet)) {
			throw new \Kdyby\Geocoder\Provider\SeznamMaps\NoResultException(sprintf('Could not execute query %s', json_encode($query)));
		}

		return $this->returnResults([array_merge($this->getDefaults(), $resultSet)]);
	}

	/**
	 * @param \SimpleXMLElement $rgeocode
	 * @param string|NULL $maximumPrecision
	 * @return mixed[]
	 */
	private function reversedToResult(SimpleXMLElement $rgeocode, $maximumPrecision = NULL)
	{
		$weights = array_flip(['addr', 'stre', 'quar', 'ward', 'muni', 'dist', 'regi', 'coun']);

		$resultSet = [];

		/** @var \SimpleXMLElement $item */
		foreach ($rgeocode->children() as $item) {
			$attrs = $item->attributes();
			/** @var \SimpleXMLElement $attrs */

			$type = (string) $attrs->type;
			if ($maximumPrecision !== NULL && (!isset($weights[$type]) || $weights[$type] < $weights[$maximumPrecision])) {
				continue; // ignore component
			}

			switch ($type) {
				case 'addr':
					$resultSet['latitude'] = (string) $attrs->y;
					$resultSet['longitude'] = (string) $attrs->x;

					if (preg_match('~' . self::RE_NUMBER . '\\s*\\z~i', (string) $attrs->name, $m)) {
						$resultSet['streetNumber'] = $m['streetNumber'];
					}

					if (empty($resultSet['streetName']) && preg_match('~^' . self::RE_STREET . '?\\s*' . self::RE_NUMBER . '\\s*\\z~i', (string) $attrs->name, $m)) {
						$resultSet['streetName'] = $m['streetName'];
					}
					break;

				case 'stre':
					$resultSet['streetName'] = preg_replace('~^(ulice\s)~', '', (string) $attrs->name);
					break;

				case 'quar': // Brno-střed, level 4
					$resultSet['adminLevels'][4] = [
						'name' => (string) $attrs->name,
						'level' => 4,
					];
					break;

				case 'ward': // Staré Brno, level 3
					$resultSet['adminLevels'][3] = [
						'name' => (string) $attrs->name,
						'level' => 3,
					];
					break;

				case 'muni':
					$resultSet['locality'] = (string) $attrs->name;
					break;

				case 'dist': // Brno-město, level 2
					$resultSet['adminLevels'][2] = [
						'name' => (string) $attrs->name,
						'level' => 2,
					];
					break;

				case 'regi': // Jihomoravský, level 1
					$resultSet['adminLevels'][1] = [
						'name' => (string) $attrs->name,
						'level' => 1,
					];
					break;

				case 'coun':
					$resultSet['country'] = (string) $attrs->name;
					break;
			}
		}

		/** @var \SimpleXMLElement $attrs */
		$attrs = $rgeocode->attributes();

		$postalCodeRegexp = sprintf('~%s(,\\s+%s)?(,\\s+%s)?(,\\s+%s)?\\z~i', self::RE_POSTAL_CODE, self::RE_DISTRICT, self::RE_REGION, self::RE_COUNTRY);
		if (preg_match($postalCodeRegexp, (string) $attrs->label, $m)) {
			$resultSet['postalCode'] = preg_replace('~\\s+~i', '', $m['postalCode']);
		}

		return $resultSet;
	}

	/**
	 * @param string $endpoint
	 * @param array $query
	 * @return \SimpleXMLElement
	 */
	protected function executeQuery($endpoint, $query)
	{
		$url = $endpoint . '?' . http_build_query($query, NULL, '&');
		$content = $this->getAdapter()->get($url)->getBody();

		if (empty($content)) {
			throw new \Kdyby\Geocoder\Provider\SeznamMaps\NoResultException(sprintf('Could not execute query "%s".', json_encode($query)));
		}

		try {
			libxml_use_internal_errors(TRUE);
			return new SimpleXMLElement($content);

		} catch (\Exception $e) {
			throw new \Kdyby\Geocoder\Provider\SeznamMaps\NoResultException(sprintf('Invalid result %s', json_encode($query)), 0, $e);
		}
	}

	/**
	 * Returns the provider's name.
	 *
	 * @return string
	 */
	public function getName()
	{
		return 'seznam_maps';
	}

}
