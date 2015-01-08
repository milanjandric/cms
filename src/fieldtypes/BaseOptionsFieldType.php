<?php
/**
 * @link http://buildwithcraft.com/
 * @copyright Copyright (c) 2013 Pixel & Tonic, Inc.
 * @license http://buildwithcraft.com/license
 */

namespace craft\app\fieldtypes;

use craft\app\Craft;
use craft\app\enums\AttributeType;
use craft\app\enums\ColumnType;
use craft\app\fieldtypes\data\MultiOptionsFieldData;
use craft\app\fieldtypes\data\OptionData;
use craft\app\fieldtypes\data\SingleOptionFieldData;
use craft\app\helpers\ArrayHelper;
use craft\app\helpers\DbHelper;

/**
 * Class BaseOptionsFieldType
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
abstract class BaseOptionsFieldType extends BaseFieldType
{
	// Properties
	// =========================================================================

	/**
	 * @var bool
	 */
	protected $multi = false;

	/**
	 * @var
	 */
	private $_options;

	// Public Methods
	// =========================================================================

	/**
	 * @inheritDoc FieldTypeInterface::defineContentAttribute()
	 *
	 * @return mixed
	 */
	public function defineContentAttribute()
	{
		if ($this->multi)
		{
			$options = $this->getSettings()->options;

			// See how much data we could possibly be saving if everything was selected.
			$length = 0;

			foreach ($options as $option)
			{
				if (!empty($option['value']))
				{
					// +3 because it will be json encoded. Includes the surrounding quotes and comma.
					$length += strlen($option['value']) + 3;
				}
			}

			if ($length)
			{
				// Add +2 for the outer brackets and -1 for the last comma.
				$length += 1;

				$columnType = DbHelper::getTextualColumnTypeByContentLength($length);
			}
			else
			{
				$columnType = ColumnType::Varchar;
			}

			return [AttributeType::Mixed, 'column' => $columnType, 'default' => $this->getDefaultValue()];
		}
		else
		{
			return [AttributeType::String, 'column' => ColumnType::Varchar, 'maxLength' => 255, 'default' => $this->getDefaultValue()];
		}
	}

	/**
	 * @inheritDoc BaseElementFieldType::getSettingsHtml()
	 *
	 * @return string|null
	 */
	public function getSettingsHtml()
	{
		$options = $this->getOptions();

		if (!$options)
		{
			// Give it a default row
			$options = [['label' => '', 'value' => '']];
		}

		return Craft::$app->templates->renderMacro('_includes/forms', 'editableTableField', [
			[
				'label'        => $this->getOptionsSettingsLabel(),
				'instructions' => Craft::t('Define the available options.'),
				'id'           => 'options',
				'name'         => 'options',
				'addRowLabel'  => Craft::t('Add an option'),
				'cols'         => [
					'label' => [
						'heading'      => Craft::t('Option Label'),
						'type'         => 'singleline',
						'autopopulate' => 'value'
					],
					'value' => [
						'heading'      => Craft::t('Value'),
						'type'         => 'singleline',
						'class'        => 'code'
					],
					'default' => [
						'heading'      => Craft::t('Default?'),
						'type'         => 'checkbox',
						'class'        => 'thin'
					],
				],
				'rows' => $options
			]
		]);
	}

	/**
	 * @inheritDoc SavableComponentTypeInterface::prepSettings()
	 *
	 * @param array $settings
	 *
	 * @return array
	 */
	public function prepSettings($settings)
	{
		if (!empty($settings['options']))
		{
			// Drop the string row keys
			$settings['options'] = array_values($settings['options']);
		}

		return $settings;
	}

	/**
	 * @inheritDoc FieldTypeInterface::prepValue()
	 *
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	public function prepValue($value)
	{
		$selectedValues = ArrayHelper::toArray($value);

		if ($this->multi)
		{
			if (is_array($value))
			{
				// Convert all the values to OptionData objects
				foreach ($value as &$val)
				{
					$label = $this->getOptionLabel($val);
					$val = new OptionData($label, $val, true);
				}
			}
			else
			{
				$value = [];
			}

			$value = new MultiOptionsFieldData($value);
		}
		else
		{
			// Convert the value to a SingleOptionFieldData object
			$label = $this->getOptionLabel($value);
			$value = new SingleOptionFieldData($label, $value, true);
		}

		$options = [];

		foreach ($this->getOptions() as $option)
		{
			$selected = in_array($option['value'], $selectedValues);
			$options[] = new OptionData($option['label'], $option['value'], $selected);
		}

		$value->setOptions($options);

		return $value;
	}

	// Protected Methods
	// =========================================================================

	/**
	 * Returns the label for the Options setting.
	 *
	 * @return string
	 */
	abstract protected function getOptionsSettingsLabel();

	/**
	 * @inheritDoc BaseSavableComponentType::defineSettings()
	 *
	 * @return array
	 */
	protected function defineSettings()
	{
		return [
			'options' => [AttributeType::Mixed, 'default' => []]
		];
	}

	/**
	 * Returns the field options, accounting for the old school way of saving them.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		if (!isset($this->_options))
		{
			$this->_options = [];

			$options = $this->getSettings()->options;

			if (is_array($options))
			{
				foreach ($options as $key => $option)
				{
					// Old school?
					if (!is_array($option))
					{
						$this->_options[] = ['label' => $option, 'value' => $key, 'default' => ''];
					}
					else
					{
						$this->_options[] = $option;
					}
				}
			}
		}

		return $this->_options;
	}

	/**
	 * Returns the field options, with labels run through Craft::t().
	 *
	 * @return array
	 */
	protected function getTranslatedOptions()
	{
		$options = $this->getOptions();

		foreach ($options as &$option)
		{
			$option['label'] = Craft::t($option['label']);
		}

		return $options;
	}

	/**
	 * Returns an option's label by its value.
	 *
	 * @param string $value
	 *
	 * @return string
	 */
	protected function getOptionLabel($value)
	{
		foreach ($this->getOptions() as $option)
		{
			if ($option['value'] == $value)
			{
				return $option['label'];
			}
		}

		return $value;
	}

	/**
	 * Returns the default field value.
	 *
	 * @return array|string|null
	 */
	protected function getDefaultValue()
	{
		if ($this->multi)
		{
			$defaultValues = [];
		}

		foreach ($this->getOptions() as $option)
		{
			if (!empty($option['default']))
			{
				if ($this->multi)
				{
					$defaultValues[] = $option['value'];
				}
				else
				{
					return $option['value'];
				}
			}
		}

		if ($this->multi)
		{
			return $defaultValues;
		}
	}
}
