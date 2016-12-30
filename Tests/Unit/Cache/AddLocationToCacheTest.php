<?php
namespace Evoweb\StoreFinder\Tests\Unit\Cache;

/***************************************************************
 * Copyright notice
 *
 * (c) 2016 Sebastian Fischer, <typo3@evoweb.de>
 * All rights reserved
 *
 * This script is part of the TYPO3 project. The TYPO3 project is
 * free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 *
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Coordinate cache test
 */
class AddLocationToCacheTest extends \TYPO3\CMS\Components\TestingFramework\Core\UnitTestCase
{
    /**
     * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var \Evoweb\StoreFinder\Cache\CoordinatesCache|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $coordinatesCache;

    /**
     * @var \TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $frontendUser;


    /**
     * Setup for tests
     *
     * @throws \InvalidArgumentException
     * @throws \PHPUnit_Framework_Exception
     * @return void
     */
    public function setUp()
    {
        $this->objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
            \TYPO3\CMS\Extbase\Object\ObjectManager::class
        );

        $this->setupDatabaseMock();
        $this->setupCaches();
    }


    /**
     * Test for something
     *
     * @test
     * @throws \PHPUnit_Framework_Exception
     * @throws \TYPO3\CMS\Core\Exception
     * @return void
     */
    public function locationWithZipCityCountryOnlyGetStoredInCacheTable()
    {
        $this->coordinatesCache->flushCache();

        $data = [
            'address' => '',
            'zipcode' => substr(mktime(), -5),
            'city' => uniqid('City'),
            'state' => '',
            'country' => uniqid('Country'),
        ];

        $constraint = $this->getConstraintStub($data);
        $coordinate = [
            'latitude' => $constraint->getLatitude(),
            'longitude' => $constraint->getLongitude(),
        ];

        $GLOBALS['TYPO3_DB']->expects($this->any())
            ->method('exec_SELECTgetSingleRow')
            ->will(self::returnValue([
                'content' => serialize($coordinate),
            ]));

        $fields = ['zipcode', 'city', 'country'];
        $this->coordinatesCache->addCoordinateForAddress($constraint, $fields);

        $fields = ['zipcode', 'city', 'country'];
        $entryIdentifier = $this->coordinatesCache->getHashForAddressWithFields($constraint, $fields);
        $this->assertEquals($coordinate, $this->coordinatesCache->getValueFromCacheTable($entryIdentifier));
    }

    /**
     * Test for something
     *
     * @test
     * @throws \PHPUnit_Framework_Exception
     * @throws \TYPO3\CMS\Core\Exception
     * @return void
     */
    public function locationWithAddressZipCityStateCountryGetStoredInCacheTableIfStreetAndStateIsEmpty()
    {
        $this->coordinatesCache->flushCache();

        $data = [
            'address' => '',
            'zipcode' => substr(mktime(), -5),
            'city' => uniqid('City'),
            'state' => '',
            'country' => uniqid('Country'),
         ];

        $constraint = $this->getConstraintStub($data);
        $coordinate = [
            'latitude' => $constraint->getLatitude(),
            'longitude' => $constraint->getLongitude(),
        ];

        $GLOBALS['TYPO3_DB']->expects($this->any())
            ->method('exec_SELECTgetSingleRow')
            ->will(self::returnValue([
                'content' => serialize($coordinate),
            ]));

        $fields = ['address', 'zipcode', 'city', 'state', 'country'];
        $this->coordinatesCache->addCoordinateForAddress($constraint, $fields);

        $fields = ['zipcode', 'city', 'country'];
        $entryIdentifier = $this->coordinatesCache->getHashForAddressWithFields($constraint, $fields);
        $this->assertEquals($coordinate, $this->coordinatesCache->getValueFromCacheTable($entryIdentifier));
    }


    /**
     * Test for something
     *
     * @test
     * @throws \TYPO3\CMS\Core\Exception
     * @return void
     */
    public function locationWithAddressZipCityCountryGetStoredInSessionCache()
    {
        $this->coordinatesCache->flushCache();

        $data = [
            'address' => uniqid('Address'),
            'zipcode' => substr(mktime(), -5),
            'city' => uniqid('City'),
            'state' => '',
            'country' => uniqid('Country'),
        ];

        $constraint = $this->getConstraintStub($data);
        $coordinate = [
            'latitude' => $constraint->getLatitude(),
            'longitude' => $constraint->getLongitude(),
        ];

        $fields = ['address', 'zipcode', 'city', 'state', 'country'];
        $this->coordinatesCache->addCoordinateForAddress($constraint, $fields);

        $fields = ['address', 'zipcode', 'city', 'country'];
        $hash = $this->coordinatesCache->getHashForAddressWithFields($constraint, $fields);
        $this->assertEquals($coordinate, $this->coordinatesCache->getValueFromSession($hash));
    }

    /**
     * Test for something
     *
     * @test
     * @throws \TYPO3\CMS\Core\Exception
     * @return void
     */
    public function locationWithAddressZipCityStateCountryGetStoredInSessionCache()
    {
        $this->coordinatesCache->flushCache();

        $data = [
            'address' => uniqid('Address'),
            'zipcode' => substr(mktime(), -5),
            'city' => uniqid('City'),
            'state' => '',
            'country' => uniqid('Country'),
        ];

        $constraint = $this->getConstraintStub($data);
        $coordinate = [
            'latitude' => $constraint->getLatitude(),
            'longitude' => $constraint->getLongitude(),
        ];

        $fields = ['address', 'zipcode', 'city', 'state', 'country'];
        $this->coordinatesCache->addCoordinateForAddress($constraint, $fields);

        $fields = ['address', 'zipcode', 'city', 'state', 'country'];
        $hash = $this->coordinatesCache->getHashForAddressWithFields($constraint, $fields);
        $this->assertEquals($coordinate, $this->coordinatesCache->getValueFromSession($hash));
    }


    /**
     * Get a constraint stub
     *
     * @param array $data
     *
     * @return \Evoweb\StoreFinder\Domain\Model\Constraint
     */
    protected function getConstraintStub($data)
    {
        /** @var \Evoweb\StoreFinder\Domain\Model\Constraint $constraint */
        $constraint = $this->objectManager->get(\Evoweb\StoreFinder\Domain\Model\Constraint::class);

        foreach ($data as $field => $value) {
            $setter = 'set' . ucfirst($field);
            if (method_exists($constraint, $setter)) {
                $constraint->{$setter}($value);
            }
        }

        $constraint->setLatitude(51.165691);
        $constraint->setLongitude(10.451526);

        return $constraint;
    }

    /**
     * @return void
     */
    protected function setupDatabaseMock()
    {
        $dbConnection = $this->getMock(
            \TYPO3\CMS\Core\Database\DatabaseConnection::class,
            ['connectDB', 'fullQuoteStr', 'exec_SELECTgetSingleRow', 'query']
        );

        $GLOBALS['TYPO3_DB'] = $dbConnection;
    }

    /**
     * @return void
     */
    protected function setupCaches()
    {
        /** @var \TYPO3\CMS\Core\Cache\CacheManager $cacheManager */
        $cacheManager = $this->objectManager->get(\TYPO3\CMS\Core\Cache\CacheManager::class);

        try {
            $cacheManager->getCache('store_finder_coordinate');
        } catch (\Exception $e) {
            /** @var \TYPO3\CMS\Core\Core\ApplicationContext $applicationContext */
            $applicationContext = \TYPO3\CMS\Core\Core\Bootstrap::getInstance()->getApplicationContext();

            /** @var \TYPO3\CMS\Core\Cache\CacheFactory $cacheFactory */
            $cacheFactory = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
                \TYPO3\CMS\Core\Cache\CacheFactory::class,
                $applicationContext,
                $cacheManager
            );
            $cacheFactory->create(
                'store_finder_coordinate',
                \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
                \TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend::class
            );
        }

        $this->coordinatesCache = $this->objectManager->get(\Evoweb\StoreFinder\Cache\CoordinatesCache::class);
    }
}
