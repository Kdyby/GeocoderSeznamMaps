<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Geocoder\Provider\SeznamMaps;

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
interface Exception
{

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class NoResultException extends \Geocoder\Exception\NoResult implements Exception
{

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class UnsupportedOperationException extends \Geocoder\Exception\UnsupportedOperation implements Exception
{

}
