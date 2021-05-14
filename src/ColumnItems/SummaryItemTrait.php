<?php

namespace Exceedone\Exment\ColumnItems;

use Exceedone\Exment\Enums\SummaryCondition;
use Exceedone\Exment\Enums\GroupCondition;
use Exceedone\Exment\Model\CustomColumn;

/**
 *
 * @property CustomColumn $custom_column
 */
trait SummaryItemTrait
{
    //for summary  --------------------------------------------------

    /**
     * Get summary condion name.
     * SUM, COUNT, MIN, MAX
     *
     * @return string|null
     */
    protected function getSummaryConditionName()
    {
        $summary_option = array_get($this->options, 'summary_condition');
        $summary_condition = is_null($summary_option) ? null : SummaryCondition::getEnum($summary_option)->lowerKey();
        return $summary_condition;
    }

    
    /**
     * Get sqlname for summary
     * Join table: true
     * Wrap: true
     *
     * @return string
     */
    public function getSummaryWrapTableColumn() : string
    {
        $options = $this->getSummaryParams();
        $value_column = $options['value_column'];
        $group_condition = $options['group_condition'];

        $summary_condition = $this->getSummaryConditionName();
        if (isset($summary_condition)) {
            // get cast
            $wrapCastColumn = $this->getCastColumn($value_column);
            $result = "$summary_condition($wrapCastColumn)";
        } elseif (isset($group_condition)) {
            $result = \DB::getQueryGrammar()->getDateFormatString($group_condition, $value_column, false);
        } else {
            $result = \Exment::wrapColumn($value_column);
        }

        return $result;
    }
    
    /**
     * Get sqlname for group by
     * Join table: true
     * Wrap: true
     * 
     * @return string group by column name
     */
    public function getGroupByWrapTableColumn() : string
    {
        $options = $this->getSummaryParams();
        $value_column = $options['value_column'];
        $group_condition = $options['group_condition'];
        
        if (isset($group_condition)) {
            $result = \DB::getQueryGrammar()->getDateFormatString($group_condition, $value_column, true);
        } else {
            $result = \Exment::wrapColumn($value_column);
        }

        return $result;
    }

    protected function getSummaryParams()
    {
        $group_condition = array_get($this->options, 'group_condition');
        $group_condition = isset($group_condition) ? GroupCondition::getEnum($group_condition) : null;

        // get value_column
        $value_column = $this->getTableColumn($this->custom_column->getQueryKey());
        
        return [
            'group_condition' => $group_condition,
            'value_column' => $value_column,
        ];
    }
    
    /**
     * Get API column name
     *
     * @return string
     */
    protected function _apiName()
    {
        return $this->uniqueName();
    }
}
