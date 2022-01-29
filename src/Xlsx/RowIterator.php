<?php

namespace Akeneo\Component\SpreadsheetParser\Xlsx;

/**
 * Row iterator for an Excel worksheet
 *
 * The iterator returns arrays of results.
 *
 * Empty values are trimmed from the right of the rows, and empty rows are skipped.
 *
 * @author    Antoine Guigan <antoine@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class RowIterator implements \Iterator
{
    /**
     * @var RowBuilderFactory
     */
    protected RowBuilderFactory $rowBuilderFactory;

    /**
     * @var ColumnIndexTransformer
     */
    protected ColumnIndexTransformer $columnIndexTransformer;

    /**
     * @var ValueTransformer
     */
    protected ValueTransformer $valueTransformer;

    /**
     * @var string
     */
    protected string $path;

    /**
     * @var array
     */
    protected array $options;

    /**
     * @var \XMLReader
     */
    protected $xml;

    /**
     * @var int
     */
    protected int $currentKey;

    /**
     * @var array
     */
    protected array $currentValue;

    /**
     * @var boolean
     */
    protected bool $valid;


    /**
     * Constructor
     *
     * @param RowBuilderFactory      $rowBuilderFactory
     * @param ColumnIndexTransformer $columnIndexTransformer
     * @param ValueTransformer       $valueTransformer
     * @param string                 $path
     * @param array                  $options
     * @param Archive                $archive                The Archive from which the path was extracted
     */
    public function __construct(
        RowBuilderFactory $rowBuilderFactory,
        ColumnIndexTransformer $columnIndexTransformer,
        ValueTransformer $valueTransformer,
        $path,
        array $options,
        Archive $archive
    ) {
        $this->rowBuilderFactory = $rowBuilderFactory;
        $this->columnIndexTransformer = $columnIndexTransformer;
        $this->valueTransformer = $valueTransformer;
        $this->path = $path;
        $this->options = $options;
        $this->archive = $archive;
    }

    /**
     * {@inheritdoc}
     */
    public function current(): mixed
    {
        return $this->currentValue;
    }

    /**
     * {@inheritdoc}
     */
    public function key(): mixed
    {
        return $this->currentKey;
    }

    /**
     * {@inheritdoc}
     */
    public function next(): void
    {
        $this->valid = false;

        $style = null;
        $type = null;
        $columnIndex = null;
        $rowBuilder = null;
        $currentKey = 0;

        while ($this->xml->read()) {
            if (\XMLReader::ELEMENT === $this->xml->nodeType) {
                switch ($this->xml->name) {
                    case 'row':
                        $currentKey = (int)$this->xml->getAttribute('r');
                        $rowBuilder = $this->rowBuilderFactory->create();
                        break;
                    case 'c':
                        $columnIndex = $this->columnIndexTransformer->transform($this->xml->getAttribute('r'));
                        $style = $this->getValue($this->xml->getAttribute('s'));
                        $type = $this->getValue($this->xml->getAttribute('t'));
                        break;
                    case 'v':
                        $rowBuilder->addValue(
                            $columnIndex,
                            $this->valueTransformer->transform($this->xml->readString(), $type, $style)
                        );
                        break;
                    case 'is':
                        $rowBuilder->addValue($columnIndex, $this->xml->readString());
                        break;
                }
            } elseif (\XMLReader::END_ELEMENT === $this->xml->nodeType) {
                switch ($this->xml->name) {
                    case 'row':
                        $currentValue = $rowBuilder->getData();
                        if (count($currentValue)) {
                            $this->currentKey = $currentKey;
                            $this->currentValue = $currentValue;
                            $this->valid = true;

                            return;
                        }
                        break;
                    case 'sheetData':
                        break 2;
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rewind(): void
    {
        if ($this->xml) {
            $this->xml->close();
        }
        $this->xml = \XMLReader::open($this->path);
        $this->next();
    }

    /**
     * {@inheritdoc}
     */
    public function valid(): bool
    {
        return $this->valid;
    }

    /**
     * Returns a normalized attribute value
     *
     * @param string $value
     *
     * @return string
     */
    protected function getValue($value): string
    {
        return $value ?? '';
    }
}
