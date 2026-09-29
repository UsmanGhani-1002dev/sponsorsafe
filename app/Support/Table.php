<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

/**
 * Server-side sort, search, filter and pagination for the shared DataTable component.
 * Only whitelisted sort keys and filter values are ever applied to a query.
 * Query string: ?q=&sort=name&dir=asc&status=current&page=2
 */
class Table
{
    public const PER_PAGE = 25;

    private array $allowedFilters = [];

    /** @param array<string, string> $sorts public sort key => column */
    private function __construct(private Request $request, private array $sorts, private string $default) {}

    public static function from(Request $request, array $sorts, string $default): self
    {
        return new self($request, $sorts, $default);
    }

    /** @param array<string, list<string>> $allowed filter name => allowed values */
    public function filters(array $allowed): self
    {
        $this->allowedFilters = $allowed;

        return $this;
    }

    public function filter(string $name, ?string $default = null): ?string
    {
        $value = $this->request->query($name);

        return is_string($value) && in_array($value, $this->allowedFilters[$name] ?? [], true) ? $value : $default;
    }

    public function search(Builder|Relation $query, array $columns): void
    {
        $term = $this->term();
        if ($term === '') {
            return;
        }
        $like = '%'.addcslashes($term, '%_\\').'%';
        $query->where(fn ($q) => collect($columns)->each(fn ($c) => $q->orWhere($c, 'like', $like)));
    }

    public function paginate(Builder|Relation $query, Closure $map): array
    {
        $query->orderBy($this->sorts[$this->sortKey()], $this->direction())->orderBy('id');
        $page = $query->paginate(self::PER_PAGE)->withQueryString();

        return [
            'data' => $page->getCollection()->map($map)->values(),
            'total' => $page->total(),
            'from' => $page->firstItem(),
            'to' => $page->lastItem(),
            'currentPage' => $page->currentPage(),
            'lastPage' => $page->lastPage(),
        ];
    }

    /** What the DataTable needs to show the current sort, search and filters. */
    public function state(): array
    {
        return [
            'q' => $this->term(),
            'sort' => $this->sortKey(),
            'dir' => $this->direction(),
            'filters' => collect($this->allowedFilters)->keys()->mapWithKeys(fn ($f) => [$f => $this->filter($f)])->all(),
        ];
    }

    private function term(): string
    {
        return mb_substr(trim((string) $this->request->query('q', '')), 0, 100);
    }

    private function sortKey(): string
    {
        $sort = $this->request->query('sort');

        return is_string($sort) && isset($this->sorts[$sort]) ? $sort : $this->default;
    }

    private function direction(): string
    {
        return $this->request->query('dir') === 'desc' ? 'desc' : 'asc';
    }
}
