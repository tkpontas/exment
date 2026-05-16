<?php

namespace Exceedone\Exment\DataItems\Grid;

use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomRelation;
use Exceedone\Exment\Model\CustomView;
use Exceedone\Exment\Enums\RelationType;
use Encore\Admin\Form;
use Encore\Admin\Widgets\Table;
use Encore\Admin\Grid\Column;
use Illuminate\Support\Collection;

class ExpansionGrid extends DefaultGrid
{
    /**
     * @param Column $grid_column
     * @param mixed $custom_view_column
     */
    protected function setGridColumn(
        Column $grid_column,
        $custom_view_column
    ): void {
        if (!isset($custom_view_column->child_table_id)) {
            return;
        }

        $child_table_id = $custom_view_column->child_table_id;

        $relation = CustomRelation::getRelationByParentChild(
            $this->custom_table,
            $child_table_id,
            RelationType::ONE_TO_MANY
        );

        if ($relation === null) {
            return;
        }

        $child_view = CustomView::getAllData($child_table_id);

        $grid_column->expand(
            /**
             * @param mixed $model
             */
            function ($model) use ($relation, $child_view): Table {

                $child_values = [];

                $child_expand_count = (int)config(
                    'exment.max_child_expand_count',
                    10
                );

                $children_values = $model
                    ->getChildrenValues($relation)
                    ->take($child_expand_count);

                foreach ($children_values as $children_value) {

                    /** @var Collection<int, mixed> $columns */
                    $columns = $child_view->custom_view_columns;

                    $child_values[] = $columns
                        ->map(
                            /**
                             * @return string|null
                             */
                            function ($child_view_column) use (
                                $child_view,
                                $children_value
                            ): ?string {

                                /** @var mixed $item */
                                $item = $child_view_column->column_item;

                                if ($item === null) {
                                    return null;
                                }

                                $item = $item->options([
                                    'grid_column' => true,
                                    'view_pivot_column' =>
                                        $child_view_column->view_pivot_column_id ?? null,
                                    'view_pivot_table' =>
                                        $child_view_column->view_pivot_table_id ?? null,
                                    'header_align' =>
                                        $child_view->header_align ?? null,
                                ]);

                                return (string)$item
                                    ->setCustomValue($children_value)
                                    ->html();
                            }
                        )
                        ->toArray();
                }

                $child_labels = $child_view
                    ->custom_view_columns
                    ->map(
                        /**
                         * @return string|null
                         */
                        function ($child_view_column): ?string {

                            /** @var mixed $item */
                            $item = $child_view_column->column_item;

                            if ($item === null) {
                                return null;
                            }

                            $item = $item->label(
                                array_get(
                                    $child_view_column,
                                    'view_column_name'
                                )
                            );

                            return $item->label();
                        }
                    )
                    ->toArray();

                return new Table($child_labels, $child_values);
            }
        );
    }
}
