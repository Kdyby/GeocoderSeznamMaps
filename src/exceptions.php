<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Geocoder\Provider\SeznamMaps;

interface Exception
{

}

class NoResultException extends \Geocoder\Exception\NoResult implements \Kdyby\Geocoder\Provider\SeznamMaps\Exception
{

}

class UnsupportedOperationException extends \Geocoder\Exception\UnsupportedOperation implements \Kdyby\Geocoder\Provider\SeznamMaps\Exception
{

}
