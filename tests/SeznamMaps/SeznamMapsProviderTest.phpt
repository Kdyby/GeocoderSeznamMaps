<?php

/**
 * Test: Kdyby\Geocoder\Provider\SeznamMaps\SeznamMapsProvider.
 *
 * @testCase
 */

namespace KdybyTests\Geocoder\Provider\SeznamMaps;

use Ivory\HttpAdapter\HttpAdapterInterface;
use Ivory\HttpAdapter\Message\ResponseInterface;
use Kdyby\Geocoder\Provider\SeznamMaps\SeznamMapsProvider;
use Mockery;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

class SeznamMapsProviderTest extends \Tester\TestCase
{

	public function testReverseAddr()
	{
		$adapter = $this->mockAdapter();

		$provider = new SeznamMapsProvider($adapter);
		$addresses = $provider->reverse('49.188170408', '16.6049509394');

		Assert::count(1, $addresses);

		$address = $addresses->first();
		Assert::same(49.188170408, $address->getLatitude());
		Assert::same(16.6049509394, $address->getLongitude());
		Assert::same('Česko', $address->getCountry()->getName());
		Assert::null($address->getCountry()->getCode());
		Assert::same('60200', $address->getPostalCode());
		Assert::same('Brno', $address->getLocality());
		Assert::same('Soukenická', $address->getStreetName());
		Assert::same('559/5', $address->getStreetNumber());

		$adminLevels = $address->getAdminLevels();
		Assert::count(4, $adminLevels);
		Assert::same('Brno-střed', $adminLevels->get(4)->getName());
		Assert::same('Staré Brno', $adminLevels->get(3)->getName());
		Assert::same('Brno-město', $adminLevels->get(2)->getName());
		Assert::same('Jihomoravský', $adminLevels->get(1)->getName());
	}

	public function testGeocodeAddr()
	{
		$adapter = $this->mockAdapter();

		$provider = new SeznamMapsProvider($adapter);
		$addresses = $provider->geocode('Soukenická 5, Brno');

		Assert::count(1, $addresses);

		$address = $addresses->first();
		Assert::same(49.188170408, $address->getLatitude());
		Assert::same(16.6049509394, $address->getLongitude());
		Assert::same('Česko', $address->getCountry()->getName());
		Assert::null($address->getCountry()->getCode());
		Assert::same('60200', $address->getPostalCode());
		Assert::same('Brno', $address->getLocality());
		Assert::same('Soukenická', $address->getStreetName());
		Assert::same('559/5', $address->getStreetNumber());

		$adminLevels = $address->getAdminLevels();
		Assert::count(4, $adminLevels);
		Assert::same('Brno-střed', $adminLevels->get(4)->getName());
		Assert::same('Staré Brno', $adminLevels->get(3)->getName());
		Assert::same('Brno-město', $adminLevels->get(2)->getName());
		Assert::same('Jihomoravský', $adminLevels->get(1)->getName());
	}

	public function testGeocodeStre()
	{
		$adapter = $this->mockAdapter();

		$provider = new SeznamMapsProvider($adapter);
		$addresses = $provider->geocode('Soukenická, Brno');

		Assert::count(1, $addresses);

		$address = $addresses->first();
		Assert::same(49.1882, round($address->getLatitude(), 4));
		Assert::same(16.6055, round($address->getLongitude(), 4));
		Assert::same('Česko', $address->getCountry()->getName());
		Assert::null($address->getCountry()->getCode());
		Assert::null($address->getPostalCode());
		Assert::same('Brno', $address->getLocality());
		Assert::same('Soukenická', $address->getStreetName());
		Assert::null($address->getStreetNumber());

		$adminLevels = $address->getAdminLevels();
		Assert::count(4, $adminLevels);
		Assert::same('Brno-střed', $adminLevels->get(4)->getName());
		Assert::same('Staré Brno', $adminLevels->get(3)->getName());
		Assert::same('Brno-město', $adminLevels->get(2)->getName());
		Assert::same('Jihomoravský', $adminLevels->get(1)->getName());
	}

	public function dataGeocodeSamples()
	{
		return [
			['Cejl 486/17, 60200 Brno, okres Brno-město', 'Cejl 17, Brno'],
			['Černická 708/10, 30100 Plzeň, okres Plzeň-město', 'Černická 10, 30100 Plzeň'],
			[' , 74245 Fulnek, okres Nový Jičín', 'Děrné'],
			[' , 74245 Fulnek, okres Nový Jičín', 'Fulnek'],
			['K Zelené louce 1484/2a, 14800 Praha, okres Hlavní město Praha', 'K Zelené louce 2a, Praha'],
			['Ostrovského 365/7, 15000 Praha, okres Hlavní město Praha', 'Ostrovského 7, 150 00 Praha 5'],
			[' , 69168 Starovičky, okres Břeclav', 'Starovičky'],
			['tř. T. G. Masaryka 1119, 73801 Frýdek-Místek, okres Frýdek-Místek', 'T.G.Masaryka 1119, 73801 Frýdek-Místek'],
			['Vánková , 18100 Praha, okres Hlavní město Praha', 'Vaňkova, Praha'],
			['MCV Brno ,  Hrotovice, okres Třebíč', 'MCV Brno, Hrotovice'],
		];
	}

	/**
	 * @dataProvider dataGeocodeSamples
	 */
	public function testGeocodeSamples($expected, $input)
	{
		$adapter = $this->mockAdapter();

		$provider = new SeznamMapsProvider($adapter);
		$addresses = $provider->limit(1)->geocode($input);

		Assert::count(1, $addresses);

		$address = $addresses->first();
		$adminLevel2 = $address->getAdminLevels()->has(2) ? $address->getAdminLevels()->get(2)->getName() : NULL;
		Assert::same($expected, sprintf(
			'%s %s, %s %s, okres %s',
			$address->getStreetName(),
			$address->getStreetNumber(),
			$address->getPostalCode(),
			$address->getLocality(),
			$adminLevel2
		));
	}

	/**
	 * @return \Mockery\Mock|\Ivory\HttpAdapter\HttpAdapterInterface
	 */
	private function mockAdapter()
	{
		$adapter = Mockery::mock(HttpAdapterInterface::class)->shouldDeferMissing();
		$adapter->shouldReceive('get')->andReturnUsing(function ($url) {
			$urlQuery = parse_url($url, PHP_URL_QUERY);
			$urlPath = parse_url($url, PHP_URL_PATH);
			parse_str($urlQuery, $queryArray);

			if ($urlPath === '/rgeocode') {
				$target = str_replace('-', '_', self::webalize(sprintf('lon%s_lat%s', $queryArray['lon'], $queryArray['lat'])));
				$targetFile = __DIR__ . '/data/rg_' . $target . '.xml';

			} elseif ($urlPath === '/geocode') { // geocode
				$target = str_replace('-', '_', self::webalize($queryArray['query']));
				$targetFile = __DIR__ . '/data/g_' . $target . '.xml';

			} else {
				throw new \LogicException(sprintf('Unexpected endpoint %s', $urlPath));
			}

			if (!file_exists($targetFile)) {
				file_put_contents($targetFile, $body = trim(file_get_contents($url)) . "\n");
			} else {
				$body = file_get_contents($targetFile);
			}

			$response = Mockery::mock(ResponseInterface::class)->shouldDeferMissing();
			$response->shouldReceive('getBody')->andReturn($body);

			return $response;
		});

		return $adapter;
	}

	protected function tearDown()
	{
		Mockery::close();
	}

	private static function webalize($string)
	{
		return strtolower(trim(preg_replace('~[^a-z0-9._]+~i', '-', $string), '-'));
	}

}

(new SeznamMapsProviderTest())->run();
