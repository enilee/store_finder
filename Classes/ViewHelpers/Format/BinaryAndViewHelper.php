<?php
namespace Evoweb\StoreFinder\ViewHelpers\Format;

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

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

class BinaryAndViewHelper extends \TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper
{
    use CompileWithRenderStatic;

    public function initializeArguments()
    {
        parent::initializeArguments();

        $this->registerArgument(
            'base',
            'int',
            'Content to be added on top',
            false
        );
        $this->registerArgument(
            'content',
            'int',
            'Base to add',
            false,
            0
        );
    }

    /**
     * Make an binary addition and return the result
     *
     * @param array $arguments
     * @param \Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     *
     * @return string
     */
    public static function renderStatic(
        array $arguments,
        \Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    ): string {
        $content = $arguments['content'];
        $base = $arguments['base'];
        return ($content ?: $renderChildrenClosure()) & $base;
    }
}
