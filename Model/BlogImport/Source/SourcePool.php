<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\ShopifyBlogExport\Model\BlogImport\Source;

use Magento\Framework\Exception\LocalizedException;

/**
 * Blog extensions that can be exported, registered in di.xml.
 */
class SourcePool
{
    /**
     * @var SourceInterface[]
     */
    private $sources;

    /**
     * @param SourceInterface[] $sources Keyed by source code
     */
    public function __construct(array $sources = [])
    {
        $this->sources = $sources;
    }

    /**
     * Sources whose tables exist, keyed by code.
     *
     * @return SourceInterface[]
     */
    public function getAvailable(): array
    {
        return array_filter($this->sources, function (SourceInterface $source) {
            return $source->isAvailable();
        });
    }

    /**
     * An available source by code.
     *
     * @param string $code
     * @return SourceInterface
     * @throws LocalizedException
     */
    public function get(string $code): SourceInterface
    {
        if (!isset($this->sources[$code]) || !$this->sources[$code]->isAvailable()) {
            throw new LocalizedException(__('The selected blog extension was not found in this Magento installation.'));
        }

        return $this->sources[$code];
    }
}
