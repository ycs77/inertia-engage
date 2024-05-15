<?php

namespace Inertia\Pagination;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class Paginator extends LengthAwarePaginator
{
    /**
     * Get the URL for a given page number.
     *
     * @param  int  $page
     * @return string
     */
    public function url($page)
    {
        if ($page <= 0) {
            $page = 1;
        }

        // If $page is 1, exclude the $page parameter
        $parameters = $page === 1 ? [] : [$this->pageName => $page];

        if (count($this->query) > 0) {
            $parameters = array_merge($this->query, $parameters);
        }

        $divide = Str::contains($this->path(), '?') ? '&' : '?';

        $queryString = count($parameters)
            ? ($divide.Arr::query($parameters))
            : '';

        return $this->path().$queryString.$this->buildFragment();
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'data' => $this->items->toArray(),
            'links' => $this->linkCollection()->toArray(),
        ];
    }
}
