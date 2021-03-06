<?php
namespace Evoweb\StoreFinder\Domain\Model;

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

class Category extends \TYPO3\CMS\Extbase\Domain\Model\Category
{
    /**
     * @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\Evoweb\StoreFinder\Domain\Model\Category>
     * @lazy
     */
    protected $children;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->children = new \TYPO3\CMS\Extbase\Persistence\ObjectStorage();
    }

    public function setChildren(\TYPO3\CMS\Extbase\Persistence\ObjectStorage $children)
    {
        $this->children = $children;
    }

    /**
     * @return Category[]|\TYPO3\CMS\Extbase\Persistence\ObjectStorage
     */
    public function getChildren()
    {
        return $this->children;
    }
}
