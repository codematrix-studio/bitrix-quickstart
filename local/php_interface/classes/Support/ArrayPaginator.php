<?php

declare(strict_types=1);

namespace Project\Support;

use InvalidArgumentException;
use LogicException;

/**
 * Paginates an in-memory array and retains the public API of Lema\\Base\\Paginator.
 *
 * Request access and HTML output are kept in explicit methods so the class can
 * also be used without a Bitrix request lifecycle.
 */
class ArrayPaginator
{
    protected static $instance = null;

    protected $pagerName = 'page';
    protected $page = 1;
    protected $perPage = 50;
    protected $elements = [];
    protected $elementsWithPageN = [];
    protected $totalCount = 0;
    protected $totalPagesCount = 1;

    public function __construct(array $elements = [], $perPage = 50, $totalCount = 0)
    {
        if ($elements !== []) {
            $this->setElements($elements);
        }

        if (!empty($perPage)) {
            $this->setPerPage($perPage);
        }

        if (!empty($totalCount)) {
            $this->totalCount = max(0, (int) $totalCount);
            $this->setTotalPagesCount();
        }
    }

    /**
     * Compatibility with the old StaticInstance::get() call.
     */
    public static function get()
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    public function setElements(array $elements = [])
    {
        $this->elements = $elements;

        return $this;
    }

    public function setPerPage($perPage = 50)
    {
        $perPage = (int) $perPage;

        if ($perPage < 1) {
            throw new InvalidArgumentException('The page size must be greater than zero.');
        }

        $this->perPage = $perPage;
        $this->setTotalPagesCount();

        return $this;
    }

    public function setPagerName($pagerName)
    {
        $pagerName = (string) $pagerName;

        if ($pagerName === '') {
            throw new InvalidArgumentException('The pager parameter name cannot be empty.');
        }

        $this->pagerName = $pagerName;

        return $this;
    }

    /**
     * Reads the current page from one of the supported PHP superglobals.
     */
    public function getPageNumber($globalArrType = 'GET')
    {
        $globalArrType = strtoupper((string) $globalArrType);
        $allowed = ['GET', 'POST', 'REQUEST', 'COOKIE', 'COOKIES', 'SESSION'];
        $sourceKey = $globalArrType === 'COOKIES' ? '_COOKIE' : '_' . $globalArrType;
        $source = in_array($globalArrType, $allowed, true)
            ? ($GLOBALS[$sourceKey] ?? [])
            : ($_REQUEST ?? []);

        $page = isset($source[$this->pagerName]) && is_scalar($source[$this->pagerName])
            ? (int) $source[$this->pagerName]
            : 1;

        return max(1, $page);
    }

    public function start($totalCount = 0)
    {
        $this->totalCount = empty($totalCount)
            ? count($this->elements)
            : max(0, (int) $totalCount);

        $this->setTotalPagesCount();
        $this->page = min($this->getPageNumber(), max(1, $this->totalPagesCount));

        return $this;
    }

    protected function setTotalPagesCount()
    {
        $this->totalPagesCount = (int) ceil($this->totalCount / $this->perPage);

        return $this;
    }

    public function getElements($page = 0)
    {
        $page = empty($page) ? $this->page : (int) $page;
        $page = max(1, min($page, max(1, $this->totalPagesCount)));
        $offset = ($page - 1) * $this->perPage;

        return array_slice($this->elements, $offset, $this->perPage);
    }

    public static function incCss($withAssets = false)
    {
        $path = '/bitrix/css/main/bootstrap.min.css';

        if ($withAssets) {
            if (!class_exists('Bitrix\\Main\\Page\\Asset')) {
                throw new LogicException('Bitrix Asset manager is not available.');
            }

            \Bitrix\Main\Page\Asset::getInstance()->addCss($path);

            return;
        }

        echo '<link href="' . htmlspecialchars($path, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" type="text/css" rel="stylesheet">';
    }

    /**
     * Renders the legacy Bootstrap pagination markup as a string.
     */
    public function renderLinks($iNumPage = 0, $nPageSize = 0, $totalCount = 0, $totalPages = 0, $pagerName = 'GET', $title = 'Товары')
    {
        global $APPLICATION;

        if (!is_object($APPLICATION) || !method_exists($APPLICATION, 'GetCurPageParam')) {
            throw new LogicException('Bitrix $APPLICATION is required to render pagination links.');
        }

        $page = empty($iNumPage) ? $this->getPageNumber($pagerName) : max(1, (int) $iNumPage);
        $pageSize = empty($nPageSize) ? $this->perPage : max(1, (int) $nPageSize);
        $count = empty($totalCount) ? $this->totalCount : max(0, (int) $totalCount);
        $pages = empty($totalPages) ? (int) ceil($count / $pageSize) : max(0, (int) $totalPages);
        $page = min($page, max(1, $pages));

        $parameterName = $this->pagerName;
        $remove = [$parameterName];
        $url = static function ($value) use ($APPLICATION, $remove, $parameterName): string {
            $query = $value === null ? '' : $parameterName . '=' . $value;
            return htmlspecialchars(
                (string) $APPLICATION->GetCurPageParam($query, $remove),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );
        };

        $from = $count === 0 ? 0 : (($page - 1) * $pageSize) + 1;
        $to = min($page * $pageSize, $count);
        $html = '<div class="pagination-block"><div class="pagination-title">'
            . htmlspecialchars((string) $title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . ' ' . $from . ' - ' . $to . ' из ' . $count
            . '</div><div class="pagination-links"><ul class="pagination">';

        if ($page > 2) {
            $html .= '<li><a href="' . $url(null) . '">Начало</a></li>';
        }

        if ($page > 1) {
            $html .= '<li><a href="' . $url($page - 1) . '">&laquo;</a></li>';
        }

        for ($i = 1; $i <= $pages; $i++) {
            if ($i === $page) {
                $html .= '<li class="active"><span>' . $i . '</span></li>';
            } else {
                $html .= '<li><a href="' . $url($i) . '">' . $i . '</a></li>';
            }
        }

        if ($page < $pages) {
            $html .= '<li><a href="' . $url($page + 1) . '">&raquo;</a></li>';
        }

        if ($page < $pages - 1) {
            $html .= '<li><a href="' . $url($pages) . '">Конец</a></li>';
        }

        return $html . '</ul></div></div>';
    }

    /**
     * Keeps the original output-oriented method for existing templates.
     */
    public function getLinks($iNumPage = 0, $nPageSize = 0, $totalCount = 0, $totalPages = 0, $pagerName = 'GET', $title = 'Товары')
    {
        echo $this->renderLinks($iNumPage, $nPageSize, $totalCount, $totalPages, $pagerName, $title);
    }
}
