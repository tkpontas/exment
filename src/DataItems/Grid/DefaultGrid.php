<?php

namespace Exceedone\Exment\DataItems\Grid;

use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Grid\Column;
use Encore\Admin\Grid\Displayers\Actions;
use Encore\Admin\Grid\Filter;
use Encore\Admin\Grid\Linker;
use Exceedone\Exment\ColumnItems;
use Exceedone\Exment\Enums;
use Exceedone\Exment\Enums\PluginEventTrigger;
use Exceedone\Exment\Enums\SearchType;
use Exceedone\Exment\Enums\SystemColumn;
use Exceedone\Exment\Form\Tools;
use Exceedone\Exment\Form\Widgets\SelectItemBox;
use Exceedone\Exment\Grid\Tools as GridTools;
use Exceedone\Exment\Model\CustomColumn;
use Exceedone\Exment\Model\CustomRelation;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomView;
use Exceedone\Exment\Model\Define;
use Exceedone\Exment\Model\Plugin;
use Exceedone\Exment\Model\RelationTable;
use Exceedone\Exment\Model\System;
use Exceedone\Exment\Model\Workflow;
use Exceedone\Exment\Services\DataImportExport;
use Exceedone\Exment\Services\PartialCrudService;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class DefaultGrid extends GridBase
{
    protected CustomTable $custom_table;

    protected CustomView $custom_view;

    public function __construct(
        CustomTable $custom_table,
        CustomView $custom_view
    ) {
        $this->custom_table = $custom_table;
        $this->custom_view = $custom_view;
    }

    public function grid(): Grid
    {
        $classname = getModelName($this->custom_table);

        /** @var Model $model */
        $model = new $classname();

        $grid = new Grid($model);

        if ($this->modal) {
            $this->gridFilterForModal($grid, $this->callback);

            $db_table_name = getDBTableName($this->custom_table);
            $grid->model()->select("{$db_table_name}.*");
        } else {
            $this->custom_view->filterSortModel(
                $grid->model(),
                ['callback' => $this->callback]
            );
        }

        $this->setCustomGridFilters($grid);

        $this->setGrid($grid);

        $this->manageRowAction($grid);

        $this->manageMenuToolButton($grid);

        $grid->getDataCallback(function (Grid $grid): void {
            $customValueCollection = $grid->getOriginalCollection();
            $this->custom_table->setSelectTableValues(
                $customValueCollection
            );
        });

        if ($this->modal) {
            $this->appendSelectItemButton($grid);
        }

        return $grid;
    }

    /**
     * @param QueryBuilder|EloquentBuilder $query
     * @param array<string,mixed> $options
     * @return QueryBuilder|EloquentBuilder
     */
    public function getQuery($query, array $options = [])
    {
        return $this->custom_view->filterSortModel(
            $query,
            $options
        );
    }

    public function setGrid($grid): void
    {
        /** @var Grid $grid */

        $custom_table = $this->custom_table;

        $grid->setHeaderAttributes(
            $this->custom_view->getHeaderOptions()
        );

        $custom_view_columns =
            $this->custom_view->custom_view_columns_cache;

        foreach ($custom_view_columns as $custom_view_column) {
            $item = $custom_view_column->column_item;

            if (!isset($item)) {
                continue;
            }

            $item = $item->label(
                array_get($custom_view_column, 'view_column_name')
            )->options([
                'grid_column' => true,
                'view_pivot_column' =>
                    $custom_view_column->view_pivot_column_id ?? null,
                'view_pivot_table' =>
                    $custom_view_column->view_pivot_table_id ?? null,
                'header_align' =>
                    $this->custom_view->header_align ?? null,
            ]);

            $className = 'column-' . $item->name();

            $column = $grid->column(
                $item->uniqueName(),
                $item->label()
            )
                ->sort($item->sortable())
                ->sortName($item->getSortName())
                ->sortCallback(
                    function (&$query, $args)
                    use ($custom_view_column): void {
                        if ($query instanceof Model) {
                            $query = $query->newQuery();
                        }

                        $this->custom_view
                            ->getSearchService()
                            ->setQuery($query)
                            ->addSelect()
                            ->orderByCustomViewColumn(
                                $custom_view_column,
                                count($args) > 0
                                    ? (string) $args[0]
                                    : 'asc'
                            );
                    }
                )
                ->style($item->gridStyle())
                ->setClasses([$className])
                ->setHeaderStyle($item->gridHeaderStyle())
                ->display(function ($v) use ($item) {
                    return $item
                        ->setCustomValue($this)
                        ->html();
                })
                ->escape(false);

            $this->setGridColumn(
                $column,
                $custom_view_column
            );
        }
        $pager_count = $this->custom_view->pager_count;

        if (
            is_null(request()->get('per_page')) &&
            isset($pager_count) &&
            is_numeric($pager_count) &&
            $pager_count > 0
        ) {
            $grid->paginate((int) $pager_count);
        }

        $grid_per_pages = stringToArray(
            config('exment.grid_per_pages')
        );

        if (empty($grid_per_pages)) {
            $grid_per_pages = Define::PAGER_GRID_COUNTS;
        }

        $grid->perPages($grid_per_pages);

        $custom_table->setQueryWith(
            $grid->model(),
            $this->custom_view
        );
    }

    protected function setGridColumn(
        $column,
        $custom_view_column
    ): void {
    }

    /**
     * @param callable|null $filter_func
     */
    protected function gridFilterForModal(
        Grid $grid,
        $filter_func
    ): void {
        System::setRequestSession(
            Define::SYSTEM_KEY_SESSION_DISABLE_DATA_URL_TAG,
            true
        );

        $modal_target_view = CustomView::getEloquent(
            request()->get('target_view_id')
        );

        $this->custom_view = CustomView::getAllData(
            $this->custom_table
        );

        if (isset($modal_target_view)) {
            $modal_target_view->filterSortModel(
                $grid->model(),
                ['callback' => $filter_func]
            );
        }

        $modal_display_table = CustomTable::getEloquent(
            request()->get('display_table_id')
        );

        $modal_custom_column = CustomColumn::getEloquent(
            request()->get('target_column_id')
        );

        if (
            !empty($modal_display_table) &&
            !empty($modal_custom_column)
        ) {
            $this->custom_table->filterDisplayTable(
                $grid->model(),
                $modal_display_table,
                [
                    'all' =>
                        $modal_custom_column
                            ->isGetAllUserOrganization(),
                ]
            );
        }

        $expand = request()->get('linkage');

        if (!is_nullorempty($expand)) {
            RelationTable::setQuery(
                $grid->model(),
                array_get($expand, 'search_type'),
                array_get($expand, 'linkage_value_id'),
                [
                    'parent_table' => CustomTable::getEloquent(
                        array_get(
                            $expand,
                            'parent_select_table_id'
                        )
                    ),
                    'child_table' => CustomTable::getEloquent(
                        array_get(
                            $expand,
                            'child_select_table_id'
                        )
                    ),
                ]
            );
        }
    }

    protected function getFilterUrl(): string
    {
        if (!$this->modal) {
            $query = array_filter(
                request()->all([
                    '_scope_',
                ])
            );
        } else {
            $query = array_filter(
                request()->all([
                    'target_view_id',
                    'display_table_id',
                    'target_column_id',
                    'linkage',
                ])
            );

            $query['modal'] = 1;
        }

        return admin_urls_query(
            'data',
            $this->custom_table->table_name,
            $query
        );
    }

    /**
     * @return array<string,string|null>
     */
    public function getFilterHtml(): array
    {
        $classname = getModelName($this->custom_table);

        /** @var Model $model */
        $model = new $classname();

        $grid = new Grid($model);

        $this->setCustomGridFilters($grid, true);

        $html = null;

        $grid->filter(
            function ($filter) use (&$html): void {
                $html = $filter->render();
            }
        );

        return [
            'html' => $html,
            'script' => \Admin::purescript()->render(),
        ];
    }

    protected function setCustomGridFilters(
        Grid $grid,
        bool $ajax = false
    ): void {
        $grid->quickSearch(
            function ($model, $input): void {
                $eloquent = $model->eloquent();

                if (
                    is_object($eloquent) &&
                    method_exists(
                        $eloquent,
                        'setSearchQueryOrWhere'
                    )
                ) {
                    $eloquent->setSearchQueryOrWhere(
                        $model,
                        $input,
                        ['searchDocument' => true]
                    );
                }
            },
            'left'
        );

        $grid->filter(
            function (Filter $filter) use ($ajax): void {
                $filter->disableIdFilter();

                $filter->setAction(
                    $this->getFilterUrl()
                );

                if (
                    $this->custom_table
                        ->enableShowTrashed() === true &&
                    !$this->modal
                ) {
                    $filter->scope(
                        'trashed',
                        exmtrans(
                            'custom_value.soft_deleted_data'
                        )
                    )->onlyTrashed();
                }

                if (
                    config(
                        'exment.custom_value_filter_ajax',
                        true
                    ) &&
                    !$ajax &&
                    !$this->modal &&
                    !boolval(
                        request()->get('execute_filter')
                    )
                ) {
                    $filter->setFilterAjax(
                        admin_urls_query(
                            'data',
                            $this->custom_table->table_name,
                            ['filter_ajax' => 1]
                        )
                    );

                    return;
                }

                $filterItems =
                    $this->getFilterColumns($filter);

                if ($filterItems->count() <= 6) {
                    foreach ($filterItems as $filterItem) {
                        $filterItem
                            ->setAdminFilter($filter);
                    }
                } else {
                    $separate = (int) floor(
                        $filterItems->count() / 2
                    );

                    $filter->column(
                        1 / 2,
                        function ($filter)
                        use ($filterItems, $separate): void {
                            for (
                                $i = 0;
                                $i < $separate;
                                $i++
                            ) {
                                $item =
                                    $filterItems->get($i);

                                if ($item !== null) {
                                    $item->setAdminFilter(
                                        $filter
                                    );
                                }
                            }
                        }
                    );
                    $filter->column(
                        1 / 2,
                        function ($filter)
                        use ($filterItems, $separate): void {
                            for (
                                $i = $separate;
                                $i < $filterItems->count();
                                $i++
                            ) {
                                $item =
                                    $filterItems->get($i);

                                if ($item !== null) {
                                    $item->setAdminFilter(
                                        $filter
                                    );
                                }
                            }
                        }
                    );
                }
            }
        );
    }

    /**
     * @return Collection<int,mixed>
     */
    protected function getFilterColumns(
        $filter
    ): Collection {
        $filterItems = [];

        $custom_view_grid_filters =
            $this->custom_view
                ->custom_view_grid_filters;

        if (
            count($custom_view_grid_filters) > 0
        ) {
            $service = $this->custom_view
                ->getSearchService()
                ->setQuery($filter->model());

            foreach (
                $custom_view_grid_filters
                as $custom_view_grid_filter
            ) {
                $service->setRelationJoin(
                    $custom_view_grid_filter
                );

                $filterItems[] =
                    $custom_view_grid_filter
                        ->column_item;
            }

            return collect($filterItems);
        }

        foreach (
            SystemColumn::getOptions([
                'grid_filter' => true,
                'grid_filter_system' => true,
            ]) as $filterKey => $filterType
        ) {
            if (
                $this->custom_table
                    ->gridFilterDisable($filterKey)
            ) {
                continue;
            }

            $filterItems[] =
                ColumnItems\SystemItem::getItem(
                    $this->custom_table,
                    $filterKey
                );
        }

        $this->setRelationFilter($filterItems);

        if (
            !is_null(
                Workflow::getWorkflowByTable(
                    $this->custom_table
                )
            )
        ) {
            foreach (
                SystemColumn::getOptions([
                    'grid_filter' => true,
                    'grid_filter_system' => false,
                ]) as $filterKey => $filterType
            ) {
                if (
                    $this->custom_table
                        ->gridFilterDisable(
                            $filterKey
                        )
                ) {
                    continue;
                }

                $filterItems[] =
                    ColumnItems\WorkflowItem::getItem(
                        $this->custom_table,
                        $filterKey
                    );
            }
        }

        $this->setColumnFilter($filterItems);

        return collect($filterItems);
    }

    /**
     * @param array<int,mixed> $filterItems
     */
    protected function setRelationFilter(
        array &$filterItems
    ): void {
        $relation =
            CustomRelation::getRelationByChild(
                $this->custom_table
            );

        if (!isset($relation)) {
            return;
        }

        if ($this->modal) {
            $searchType = array_get(
                request()->get('linkage'),
                'search_type'
            );

            if (
                isMatchString(
                    $searchType,
                    $relation->relation_type
                )
            ) {
                return;
            }
        }

        $column_item =
            ColumnItems\ParentItem
                ::getItemWithRelation(
                    $this->custom_table,
                    $relation
                );

        $filterItems[] = $column_item;
    }

    /**
     * @param array<int,mixed> $filterItems
     */
    protected function setColumnFilter(
        array &$filterItems
    ): void {
        $search_column_select = null;
        $searchType = null;

        if ($this->modal) {
            $linkage = request()->get('linkage');

            $searchType = array_get(
                $linkage,
                'search_type'
            );

            $parent_table =
                CustomTable::getEloquent(
                    array_get(
                        $linkage,
                        'parent_select_table_id'
                    )
                );

            $child_table =
                CustomTable::getEloquent(
                    array_get(
                        $linkage,
                        'child_select_table_id'
                    )
                );

            if (
                isset($parent_table) &&
                isset($child_table)
            ) {
                $search_column_select =
                    $child_table
                        ->getSelectTableColumns(
                            $parent_table
                        )
                        ->first();
            }
        }

        $search_enabled_columns =
            $this->custom_table
                ->getSearchEnabledColumns();

        foreach (
            $search_enabled_columns
            as $search_column
        ) {
            if ($this->modal) {
                if (
                    isMatchString(
                        $searchType,
                        SearchType::SELECT_TABLE
                    ) &&
                    isset($search_column_select) &&
                    isMatchString(
                        $search_column_select->id,
                        $search_column->id
                    )
                ) {
                    continue;
                }
            }

            $filterItems[] =
                $search_column->column_item;
        }
    }

    protected function manageMenuToolButton(
        Grid $grid
    ): void {
        if ($this->modal) {
            $grid->disableRowSelector();
            $grid->disableCreateButton();
            $grid->disableExport();

            return;
        }

        $grid->disableCreateButton();
        $grid->disableExport();

        $service =
            $this->getImportExportService(
                $grid
            );

        $grid->exporter($service);

        $grid->tools(
            function (Grid\Tools $tools)
            use ($grid): void {
                $listButtons =
                    Plugin::pluginPreparingButton(
                        PluginEventTrigger
                            ::GRID_MENUBUTTON,
                        $this->custom_table
                    );

                $import =
                    $this->custom_table
                        ->enableImport();

                $export =
                    $this->custom_table
                        ->enableExport();
                if (
                    $import === true ||
                    $export === true
                ) {
                    /** @var mixed $button */
                    $button =
                        new Tools\ExportImportButton(
                            admin_urls(
                                'data',
                                $this->custom_table
                                    ->table_name
                            ),
                            $grid,
                            $export === true,
                            $export === true,
                            $import === true,
                            $export === true
                        );

                    $tools->append(
                        $button->setCustomTable(
                            $this->custom_table
                        )
                    );
                }

                if (
                    $this->custom_table
                        ->enableCreate(true) === true
                ) {
                    $tools->append(
                        view(
                            'exment::custom-value.new-button',
                            [
                                'table_name' =>
                                    $this->custom_table
                                        ->table_name,
                            ]
                        )->render()
                    );
                }

                if (
                    $this->custom_table
                        ->enableTableMenuButton()
                ) {
                    /** @var mixed $tool */
                    $tool =
                        new Tools\CustomTableMenuButton(
                            'data',
                            $this->custom_table
                        );

                    $tools->append($tool);
                }

                if (
                    $this->custom_table
                        ->enableViewMenuButton()
                ) {
                    /** @var mixed $tool */
                    $tool =
                        new Tools\CustomViewMenuButton(
                            $this->custom_table,
                            $this->custom_view
                        );

                    $tools->append($tool);
                }

                if (
                    $listButtons !== null &&
                    count($listButtons) > 0
                ) {
                    foreach (
                        $listButtons as $listButton
                    ) {
                        /** @var mixed $tool */
                        $tool =
                            new Tools\PluginMenuButton(
                                $listButton,
                                $this->custom_table
                            );

                        $tools->append($tool);
                    }
                }

                $tools->batch(
                    function ($batch): void {
                        if (
                            $this->custom_table
                                ->enableEdit() === true
                        ) {
                            if (
                                request()->get('_scope_')
                                    === 'trashed' &&
                                $this->custom_table
                                    ->enableShowTrashed()
                                    === true
                            ) {
                                $batch
                                    ->disableDelete();

                                $batch->add(
                                    exmtrans(
                                        'custom_value.restore'
                                    ),
                                    new GridTools
                                        \BatchRestore()
                                );

                                $batch->add(
                                    exmtrans(
                                        'custom_value.hard_delete'
                                    ),
                                    new GridTools
                                        \BatchHardDelete(
                                            exmtrans(
                                                'custom_value.hard_delete'
                                            )
                                        )
                                );
                            } else {
                                foreach (
                                    $this->custom_table
                                        ->custom_operations
                                    as $custom_operation
                                ) {
                                    if (
                                        $custom_operation
                                            ->matchOperationType(
                                                Enums
                                                    \CustomOperationType
                                                    ::BULK_UPDATE
                                            )
                                    ) {
                                        $title =
                                            $custom_operation
                                                ->getOption(
                                                    'button_label'
                                                ) ??
                                            $custom_operation
                                                ->operation_name;

                                        $batch->add(
                                            $title,
                                            new GridTools
                                                \BatchUpdate(
                                                    $custom_operation
                                                )
                                        );
                                    }
                                }
                            }
                        } else {
                            $batch->disableDelete();
                        }
                    }
                );
            }
        );
    }

    protected function manageRowAction(
        Grid $grid
    ): void {
        if ($this->modal) {
            $grid->disableActions();

            return;
        }

        if (!isset($this->custom_table)) {
            return;
        }

        $custom_table = $this->custom_table;

        $relationTables =
            $custom_table->getRelationTables();

        $grid->actions(
            function (Actions $actions)
            use (
                $custom_table,
                $relationTables
            ): void {
                /** @var mixed $row */
                $row = $actions->row;

                /** @phpstan-ignore-next-line */
                $custom_table->setGridAuthoritable(
                    $actions->grid
                        ->getOriginalCollection()
                );

                $enableEdit = true;
                $enableDelete = true;
                $enableHardDelete = false;

                if (
                    count($relationTables) > 0
                ) {
                    $linker =
                        (new Linker())
                            ->url(
                                $row
                                    ->getRelationSearchUrl()
                            )
                            ->icon('fa-compress')
                            ->tooltip(
                                exmtrans(
                                    'search.header_relation'
                                )
                            );

                    $actions->prepend($linker);
                }

                if (
                    $row->trashed() &&
                    $custom_table
                        ->enableEdit() === true &&
                    $custom_table
                        ->enableShowTrashed() === true
                ) {
                    $enableHardDelete = true;
                }

                if (
                    $row->enableEdit(true)
                        !== true
                ) {
                    $enableEdit = false;
                }

                if (
                    $row->enableDelete(true)
                        !== true
                ) {
                    $enableDelete = false;
                }
                $parent_value =
                    $row->getParentValue();

                if (
                    !is_null($parent_value) &&
                    $parent_value
                        ->enableEdit(true) !== true
                ) {
                    $enableEdit = false;
                    $enableDelete = false;
                }

                if (!$enableEdit) {
                    $actions->disableEdit();
                }

                if (!$enableDelete) {
                    $actions->disableDelete();
                }

                if ($enableHardDelete) {
                    $actions->disableView();
                    $actions->disableDelete();

                    $restoreUrl =
                        $row->getUrl()
                        . '/restoreClick';

                    $linker =
                        (new Linker())
                            ->icon('fa-undo')
                            ->script(true)
                            ->linkattributes([
                                'data-add-swal' =>
                                    $restoreUrl,
                                'data-add-swal-title' =>
                                    exmtrans(
                                        'custom_value.restore'
                                    ),
                                'data-add-swal-text' =>
                                    exmtrans(
                                        'custom_value.message.restore'
                                    ),
                                'data-add-swal-method' =>
                                    'get',
                                'data-add-swal-confirm' =>
                                    trans(
                                        'admin.confirm'
                                    ),
                                'data-add-swal-cancel' =>
                                    trans(
                                        'admin.cancel'
                                    ),
                            ])
                            ->tooltip(
                                exmtrans(
                                    'custom_value.restore'
                                )
                            );

                    $actions->append($linker);

                    $showUrl =
                        $row->getUrl()
                        . '?trashed=1';

                    $linker =
                        (new Linker())
                            ->url($showUrl)
                            ->icon('fa-eye')
                            ->tooltip(
                                trans('admin.show')
                            );

                    $actions->append($linker);

                    $deleteUrl =
                        $row->getUrl()
                        . '?trashed=1';

                    $linker =
                        (new Linker())
                            ->icon('fa-trash')
                            ->script(true)
                            ->linkattributes([
                                'data-add-swal' =>
                                    $deleteUrl,
                                'data-add-swal-title' =>
                                    exmtrans(
                                        'custom_value.hard_delete'
                                    ),
                                'data-add-swal-text' =>
                                    exmtrans(
                                        'custom_value.message.hard_delete'
                                    ),
                                'data-add-swal-method' =>
                                    'delete',
                                'data-add-swal-confirm' =>
                                    trans(
                                        'admin.confirm'
                                    ),
                                'data-add-swal-cancel' =>
                                    trans(
                                        'admin.cancel'
                                    ),
                            ])
                            ->tooltip(
                                exmtrans(
                                    'custom_value.hard_delete'
                                )
                            );

                    $actions->append($linker);
                }

                PartialCrudService
                    ::setGridRowAction(
                        $custom_table,
                        $actions
                    );
            }
        );
    }

    public function import(
        Request $request
    ): JsonResponse {
        $service =
            $this->getImportExportService()
                ->format(
                    $request->file(
                        'custom_table_file'
                    )
                )
                ->filebasename(
                    $this->custom_table
                        ->table_name
                );

        $result = $service->import($request);

        return getAjaxResponse($result);
    }

    public function getImportExportService(
        ?Grid $grid = null
    ) {
        return (
            new DataImportExport
                \DataImportExportService()
        )
            ->exportAction(
                new DataImportExport
                    \Actions\Export
                    \CustomTableAction([
                        'custom_table' =>
                            $this->custom_table,
                        'grid' => $grid,
                    ])
            )
            ->viewExportAction(
                new DataImportExport
                    \Actions\Export\ViewAction([
                        'custom_table' =>
                            $this->custom_table,
                        'custom_view' =>
                            $this->custom_view,
                        'grid' => $grid,
                    ])
            )
            ->pluginExportAction(
                new DataImportExport
                    \Actions\Export
                    \PluginAction([
                        'custom_table' =>
                            $this->custom_table,
                        'custom_view' =>
                            $this->custom_view,
                        'grid' => $grid,
                    ])
            )
            ->importAction(
                new DataImportExport
                    \Actions\Import
                    \CustomTableAction([
                        'custom_table' =>
                            $this->custom_table,
                        'primary_key' =>
                            app('request')
                                ->input(
                                    'select_primary_key'
                                ) ?? null,
                    ])
            );
    }

    public function renderModalFrame()
    {
        $custom_column =
            CustomColumn::getEloquent(
                request()->get(
                    'target_column_id'
                )
            );

        $target_column_class =
            request()->get(
                'target_column_class'
            );

        $target_column_multiple =
            request()->get(
                'target_column_multiple'
            ) ??
            (
                isset($custom_column)
                    ? (bool) $custom_column
                        ->getOption(
                            'multiple_enabled'
                        )
                    : false
            );
        $widgetmodal_uuid =
            request()->get(
                'widgetmodal_uuid'
            );

        $items = $this->custom_table
            ->getValueQuery()
            ->whereOrIn(
                'id',
                stringToArray(
                    request()->get(
                        'selected_items'
                    )
                )
            )
            ->get();

        $url =
            request()->fullUrl()
            . '&modal=1';

        return getAjaxResponse([
            'title' =>
                trans('admin.search')
                . ' : '
                . $this->custom_table
                    ->table_view_name,

            'body' =>
                (
                    new SelectItemBox(
                        $url,
                        $target_column_class,
                        $widgetmodal_uuid,
                        [[
                            'name' => 'select',
                            'label' =>
                                trans(
                                    'admin.choose'
                                ),
                            'multiple' =>
                                (bool)
                                $target_column_multiple,
                            'icon' =>
                                $this->custom_table
                                    ->getOption(
                                        'icon'
                                    ),
                            'background_color' =>
                                $this->custom_table
                                    ->getOption(
                                        'color'
                                    )
                                ?? '#3c8dbc',
                            'color' =>
                                '#FFFFFF',
                            'items' =>
                                $items->map(
                                    function ($item) {
                                        return [
                                            'value' =>
                                                $item->id,
                                            'label' =>
                                                $item
                                                    ->getLabel(),
                                        ];
                                    }
                                )->toArray(),
                        ]]
                    )
                )->render(),

            'submitlabel' =>
                trans('admin.setting'),

            'modalSize' => 'modal-xl',

            'modalClass' =>
                'modal-selectitem '
                . 'modal-heightfix '
                . 'modal-body-overflow-hidden',

            'preventSubmit' => true,
        ]);
    }

    public function renderModal(
        Grid $grid
    ) {
        return view(
            'exment::widgets.partialindex',
            [
                'content' => $grid->render(),
            ]
        );
    }

    protected function appendSelectItemButton(
        Grid $grid
    ): void {
        $grid->column(
            'modal_selectitem',
            trans('admin.action')
        )
            ->display(
                function (
                    $a,
                    $b,
                    $model
                ) {
                    return view(
                        'exment::tools.selectitem-button',
                        [
                            'model' => $model,
                            'value' =>
                                $model->id,
                            'valueLabel' =>
                                $model
                                    ->getLabel(),
                            'label' =>
                                exmtrans(
                                    'common.append_to_selectitem'
                                ),
                            'target_selectitem' =>
                                'select',
                        ]
                    )->render();
                }
            )
            ->escape(false);
    }

    /**
     * @param mixed $view_kind_type
     * @param array<string,mixed> $options
     */
    public static function setViewForm(
        $view_kind_type,
        $form,
        $custom_table,
        array $options = []
    ): void {
        if (
            in_array(
                $view_kind_type,
                [
                    Enums\ViewKindType::DEFAULT,
                    Enums\ViewKindType::ALLDATA,
                ],
                true
            )
        ) {
            $grid_per_pages =
                stringToArray(
                    config(
                        'exment.grid_per_pages'
                    )
                );

            if (empty($grid_per_pages)) {
                $grid_per_pages =
                    Define::PAGER_GRID_COUNTS;
            }

            $form->select(
                'pager_count',
                exmtrans(
                    'common.pager_count'
                )
            )
                ->required()
                ->options(
                    getPagerOptions(
                        true,
                        $grid_per_pages
                    )
                )
                ->disableClear()
                ->default(0);

            $form->select(
                'header_align',
                exmtrans(
                    'custom_view.header_align'
                )
            )->options(
                Enums\TextAlignExType
                    ::transArray(
                        'custom_view.align_type_options'
                    )
            );
        }

        if (
            $view_kind_type
                != Enums\ViewKindType::FILTER
        ) {
            static::setViewInfoboxFields(
                $form
            );

            static::setColumnFields(
                $form,
                $custom_table
            );
        }

        if (
            $view_kind_type
                != Enums\ViewKindType::ALLDATA
        ) {
            static::setFilterFields(
                $form,
                $custom_table
            );
        }

        static::setSortFields(
            $form,
            $custom_table,
            true
        );

        if (
            in_array(
                $view_kind_type,
                [
                    Enums\ViewKindType::DEFAULT,
                    Enums\ViewKindType::ALLDATA,
                ],
                true
            )
        ) {
            static::setGridFilterFields(
                $form,
                $custom_table
            );
        }
    }
    /**
     * @param array<string,mixed> $column_options
     */
    public static function setGridFilterFields(
        &$form,
        $custom_table,
        array $column_options = []
    ): void {
        $column_options = array_merge(
            [
                'append_table' => true,
                'include_parent' => true,
                'include_workflow' => true,
                'index_enabled_only' => true,
                'only_system_grid_filter' => true,
                'ignore_many_to_many' => true,
                'ignore_multiple_refer' => true,
            ],
            $column_options
        );

        $manualUrl = getManualUrl(
            'column?id='
            . exmtrans(
                'custom_column.options.index_enabled'
            )
        );

        $form->hasManyTable(
            'custom_view_grid_filters',
            exmtrans(
                'custom_view.custom_view_grid_filters'
            ),
            function ($form)
            use (
                $custom_table,
                $column_options
            ): void {
                $targetOptions =
                    $custom_table
                        ->getColumnsSelectOptions(
                            $column_options
                        );

                $field = $form->select(
                    'view_column_target',
                    exmtrans(
                        'custom_view.view_column_target'
                    )
                )
                    ->required()
                    ->options($targetOptions);

                if (
                    (bool) config(
                        'exment.form_column_option_group',
                        false
                    )
                ) {
                    $targetGroups =
                        static::convertGroups(
                            $targetOptions,
                            $custom_table
                        );

                    $field->groups(
                        $targetGroups
                    );
                }

                $form->hidden('order')
                    ->default(0);
            }
        )
            ->setTableColumnWidth(
                8,
                4
            )
            ->rowUpDown(
                'order',
                10
            )
            ->descriptionHtml(
                exmtrans(
                    'custom_view.description_custom_view_grid_filters',
                    $manualUrl
                )
            );
    }
}
