<?php
namespace Evoweb\StoreFinder\Controller;

/**
 * This file is developed by evoweb.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

use Evoweb\StoreFinder\Validation\Validator\SettableInterface;
use Evoweb\StoreFinder\Domain\Model;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Property\PropertyMappingConfiguration;
use TYPO3\CMS\Extbase\Property\TypeConverter\PersistentObjectConverter;

class MapController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{
    /**
     * @var \Evoweb\StoreFinder\Domain\Repository\LocationRepository
     */
    public $locationRepository;

    /**
     * @var \Evoweb\StoreFinder\Domain\Repository\CategoryRepository
     */
    protected $categoryRepository;

    /**
     * @var \Evoweb\StoreFinder\Service\GeocodeService
     */
    protected $geocodeService;

    public function injectLocationRepository(
        \Evoweb\StoreFinder\Domain\Repository\LocationRepository $locationRepository
    ) {
        $this->locationRepository = $locationRepository;
    }

    public function injectCategoryRepository(
        \Evoweb\StoreFinder\Domain\Repository\CategoryRepository $categoryRepository
    ) {
        $this->categoryRepository = $categoryRepository;
    }

    public function injectGeocodeService(
        \Evoweb\StoreFinder\Service\GeocodeService $geocodeService
    ) {
        $this->geocodeService = $geocodeService;
    }

    public function injectDispatcher(
        \TYPO3\CMS\Extbase\SignalSlot\Dispatcher $signalSlotDispatcher
    ) {
        $this->signalSlotDispatcher = $signalSlotDispatcher;
    }

    protected function initializeActionMethodValidators()
    {
        if ($this->arguments->hasArgument('search')) {
            $this->modifyValidatorsBasedOnSettings(
                $this->arguments->getArgument('search'),
                $this->settings['validation'] ?? []
            );
        } else {
            parent::initializeActionMethodValidators();
        }
    }

    protected function modifyValidatorsBasedOnSettings(
        \TYPO3\CMS\Extbase\Mvc\Controller\Argument $argument,
        array $configuredValidators
    ) {
        /** @var \Evoweb\StoreFinder\Validation\ValidatorResolver $validatorResolver */
        $validatorResolver = $this->objectManager->get(\Evoweb\StoreFinder\Validation\ValidatorResolver::class);

        /** @var \Evoweb\StoreFinder\Validation\Validator\ConstraintValidator $validator */
        $validator = $this->objectManager->get(\Evoweb\StoreFinder\Validation\Validator\ConstraintValidator::class);
        foreach ($configuredValidators as $fieldName => $configuredValidator) {
            if (!is_array($configuredValidator)) {
                $validatorInstance = $this->getValidatorByConfiguration(
                    $configuredValidator,
                    $validatorResolver
                );

                if ($validatorInstance instanceof SettableInterface) {
                    $validatorInstance->setPropertyName($fieldName);
                }
            } else {
                $validatorInstance = $this->objectManager->get(
                    \Evoweb\StoreFinder\Validation\Validator\ConstraintValidator::class
                );
                foreach ($configuredValidator as $individualConfiguredValidator) {
                    $individualValidatorInstance = $this->getValidatorByConfiguration(
                        $individualConfiguredValidator,
                        $validatorResolver
                    );

                    if ($individualValidatorInstance instanceof SettableInterface) {
                        $individualValidatorInstance->setPropertyName($fieldName);
                    }

                    $validatorInstance->addValidator($individualValidatorInstance);
                }
            }

            $validator->addPropertyValidator($fieldName, $validatorInstance);
        }

        $argument->setValidator($validator);
    }

    /**
     * @param string $configuration
     * @param \Evoweb\StoreFinder\Validation\ValidatorResolver $validatorResolver
     *
     * @return \TYPO3\CMS\Extbase\Validation\Validator\ValidatorInterface
     */
    protected function getValidatorByConfiguration(string $configuration, $validatorResolver)
    {
        $validateAnnotation = $validatorResolver->getParsedValidatorAnnotation($configuration);
        $currentValidator = current($validateAnnotation['validators']);

        $validatorObject = $validatorResolver->createValidator(
            $currentValidator['validatorName'],
            (array) $currentValidator['validatorOptions']
        );

        return $validatorObject;
    }

    protected function setTypeConverter()
    {
        if ($this->request->hasArgument('search')) {
            /** @var array $search */
            $search = $this->request->getArgument('search');
            if (!is_array($search['category'])) {
                $search['category'] = [$search['category']];
                $this->request->setArgument('search', $search);
            }
            /** @var PropertyMappingConfiguration $configuration */
            $configuration = $this->arguments['search']->getPropertyMappingConfiguration();
            $configuration->allowProperties('category');
            $configuration->setTypeConverterOption(
                PersistentObjectConverter::class,
                PersistentObjectConverter::CONFIGURATION_CREATION_ALLOWED,
                true
            );
        }
    }

    /**
     * Initializes the controller before invoking an action method.
     * Override this method to solve tasks which all actions have in
     * common.
     */
    protected function initializeAction()
    {
        if (isset($this->settings['override']) && is_array($this->settings['override'])) {
            $override = $this->settings['override'];
            unset($this->settings['override']);

            $this->settings = array_merge($this->settings, $override);
        }

        $this->settings['allowedCountries'] = explode(',', $this->settings['allowedCountries']);
        $this->geocodeService->setSettings($this->settings);
        $this->locationRepository->setSettings($this->settings);

        $this->setTypeConverter();
    }

    /**
     * Action responsible for rendering search, map and list partial
     *
     * @param Model\Constraint $search
     *
     * @validate $search Evoweb.StoreFinder:Constraint
     */
    public function mapAction(Model\Constraint $search = null)
    {
        if ($search !== null) {
            $this->getLocationsByConstraints($search);
        } elseif ($this->settings['location']) {
            $this->forward('show');
        } else {
            $this->getLocationsByDefaultConstraints();
        }

        $this->addCategoryFilterToView();
        $this->view->assign('search', $search);
        $this->view->assign(
            'static_info_tables',
            \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('static_info_tables') ? 1 : 0
        );
    }

    protected function getLocationsByConstraints(Model\Constraint $search)
    {
        $search = $this->addDefaultConstraint($search);
        $search = $this->geocodeService->geocodeAddress($search);
        $this->view->assign('searchWasNotClearEnough', $this->geocodeService->hasMultipleResults);

        $locations = $this->locationRepository->findByConstraint($search);

        /** @var Model\Constraint $search */
        /** @var QueryResultInterface $locations */
        list($search, $locations) = $this->signalSlotDispatcher->dispatch(
            __CLASS__,
            'mapActionWithConstraint',
            [$search, $locations, $this]
        );

        $center = $this->getCenter($search);
        $center = $this->setZoomLevel($center, $locations);
        $this->view->assign('center', $center);

        $this->view->assign('numberOfLocations', is_object($locations) ? $locations->count() : count($locations));
        $this->view->assign('locations', $locations);
        $this->view->assign('afterSearch', 1);
    }

    protected function getLocationsByDefaultConstraints()
    {
        /** @var Model\Constraint $search */
        $search = $this->objectManager->get(\Evoweb\StoreFinder\Domain\Model\Constraint::class);
        $locations = false;

        if ($this->settings['showBeforeSearch'] & 2 && is_array($this->settings['defaultConstraint'])) {
            $search = $this->addDefaultConstraint($search);
            if (intval($this->settings['geocodeDefaultConstraint']) === 1) {
                $search = $this->geocodeService->geocodeAddress($search);
            }
            $this->view->assign('searchWasNotClearEnough', $this->geocodeService->hasMultipleResults);

            if ($this->settings['showLocationsForDefaultConstraint']) {
                $locations = $this->locationRepository->findByConstraint($search);
            } else {
                $locations = $this->locationRepository->findOneByUid(-1);
            }
        }

        if ($this->settings['showBeforeSearch'] & 4) {
            $this->locationRepository->setDefaultOrderings([
                'zipcode' => \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_ASCENDING,
                'city' => \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_ASCENDING,
                'name' => \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_ASCENDING,
            ]);

            $locations = $this->locationRepository->findAll();
        }

        if ($locations) {
            $center = $this->getCenter($search);
            $center = $this->setZoomLevel($center, $locations);
            $this->view->assign('center', $center);

            $this->view->assign('numberOfLocations', count($locations));
            $this->view->assign('locations', $locations);
        }
    }

    /**
     * Render a map with only one location
     *
     * @param Model\Location $location
     */
    public function showAction(Model\Location $location = null)
    {
        if ($location === null) {
            if ($this->settings['location']) {
                $location = $this->locationRepository->findByUid((int) $this->settings['location']);
            }
        }

        if ($location !== null) {
            /** @var Model\Location $center */
            $center = $location;
            $center->setZoom($this->settings['zoom'] ? $this->settings['zoom'] : 15);

            $this->view->assign('center', $center);
            $this->view->assign('numberOfLocations', 1);
            $this->view->assign('locations', [$location]);
        }
    }

    /**
     * Add categories give in settings to view
     */
    protected function addCategoryFilterToView()
    {
        if ($this->settings['categories']) {
            $categories = $this->categoryRepository->findByUids(
                GeneralUtility::intExplode(',', $this->settings['categories'], true)
            );

            $this->view->assign('categories', $categories);
        }
    }

    /**
     * Get center from query result based on center of all coordinates. If only one
     * is found this is used. In case none was found the center based on the request
     * gets calculated
     *
     * @param QueryResultInterface $queryResult
     *
     * @return Model\Location|Model\Constraint
     */
    protected function getCenterOfQueryResult(QueryResultInterface $queryResult)
    {
        if ($queryResult->count() == 1) {
            /** @var \Evoweb\StoreFinder\Domain\Model\Location $center */
            $center = $queryResult->getFirst();
            return $center;
        } elseif (!$queryResult->count()) {
            return $this->getCenter();
        }

        /** @var Model\Location $center */
        $center = $this->objectManager->get(\Evoweb\StoreFinder\Domain\Model\Location::class);

        $x = $y = $z = 0;
        /** @var Model\Location $location */
        foreach ($queryResult as $location) {
            $x += cos($location->getLatitude()) * cos($location->getLongitude());
            $y += cos($location->getLatitude()) * sin($location->getLongitude());
            $z += sin($location->getLatitude());
        }

        $x /= $queryResult->count();
        $y /= $queryResult->count();
        $z /= $queryResult->count();

        $center->setLongitude(atan2($y, $x));
        $center->setLatitude(atan2($z, sqrt($x * $x + $y * $y)));

        return $center;
    }

    /**
     * Add default constraints configured in typoscript and only set if property
     * in search is empty
     *
     * @param Model\Constraint $search
     *
     * @return Model\Constraint
     */
    protected function addDefaultConstraint(Model\Constraint $search): Model\Constraint
    {
        $defaultConstraint = $this->settings['defaultConstraint'];

        foreach ($defaultConstraint as $property => $value) {
            $getter = 'get' . ucfirst($property);
            $setter = 'set' . ucfirst($property);
            if (method_exists($search, $setter) && !$search->{$getter}()) {
                $search->{$setter}($value);
            }
        }

        return $search;
    }

    /**
     * Geocode requested address and use as center or fetch location that was
     * flagged as center.
     *
     * @param Model\Constraint $constraint
     *
     * @return Model\Location|Model\Constraint
     */
    public function getCenter(Model\Constraint $constraint = null)
    {
        $center = null;

        if ($constraint !== null) {
            if ($constraint->getLatitude() && $constraint->getLongitude()) {
                /** @var Model\Location $center */
                $center = $this->objectManager->get(\Evoweb\StoreFinder\Domain\Model\Location::class);
                $center->setLatitude($constraint->getLatitude());
                $center->setLongitude($constraint->getLongitude());
            } else {
                $center = $this->geocodeService->geocodeAddress($constraint);
            }
        }

        if ($center === null) {
            $center = $this->locationRepository->findOneByCenter();
        }

        if ($center === null) {
            $center = $this->locationRepository->findCenterByLatitudeAndLongitude();
        }

        return $center;
    }

    /**
     * Set zoom level for map based on radius
     *
     * @param Model\Location $center
     * @param QueryResultInterface $locations
     *
     * @return Model\Location
     */
    public function setZoomLevel($center, $locations)
    {
        $radius = false;
        /** @var Model\Location $location */
        foreach ($locations as $location) {
            $distance = $location->getDistance();
            $radius = $distance > $radius ? $distance : $radius;
        }

        if ($radius === false) {
            $radius = $this->settings['defaultConstraint']['radius'];
        }

        if ($radius > 500 && $radius <= 1000) {
            $zoom = 12;
        } elseif ($radius < 2) {
            $zoom = 2;
        } elseif ($radius < 3) {
            $zoom = 3;
        } elseif ($radius < 5) {
            $zoom = 4;
        } elseif ($radius < 15) {
            $zoom = 6;
        } elseif ($radius <= 25) {
            $zoom = 7;
        } elseif ($radius <= 100) {
            $zoom = 9;
        } elseif ($radius <= 300) {
            $zoom = 10;
        } elseif ($radius <= 500) {
            $zoom = 11;
        } else {
            $zoom = 13;
        }

        $center->setZoom(18 - $zoom);

        return $center;
    }

    public function getSettings(): array
    {
        return $this->settings;
    }

    protected function getErrorFlashMessage(): string
    {
        return '';
    }
}
