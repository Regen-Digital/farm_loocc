<?php

/**
 * @file
 * Provide Views data for farm_loocc.module.
 */

/**
 * Implements hook_views_data().
 */
function farm_loocc_views_data() {

  // Build array of views data.
  $data = [];

  // Base LOOC-C estimate table.
  $data['farm_loocc_estimate'] = [
    'table' => [
      'group' => t('LOOC-C estimate'),
      'base' => [
        'title' => t('Base LOOC-C estimates'),
        'help' => t('Metadata for LOOC-C estimates.'),
      ],
    ],
    'id' => [
      'title' => t('The estimate ID'),
      'help' => t('Unique ID of the base estimate.'),
      'field' => [
        'id' => 'standard',
        'click sortable' => TRUE,
      ],
    ],
    'asset_id' => [
      'title' => t('Location asset ID'),
      'help' => t('ID of the location asset associated with the estimate.'),
      'relationship' => [
        'base' => 'asset_field_data',
        'base_field' => 'id',
        'id' => 'standard',
        'label' => t('Location asset'),
      ],
    ],
    'timestamp' => [
      'title' => t('Estimate timestamp'),
      'help' => t('Timestamp the LOOC-C estimate was taken.'),
      'field' => [
        'id' => 'date',
        'click sortable' => TRUE,
      ],
      'sort' => [
        'id' => 'date',
      ],
      'filter' => [
        'id' => 'date',
      ],
    ],
    'new_irrigation' => [
      'title' => t('New irrigation'),
      'help' => t('If the project will be using new irrigation methods.'),
      'field' => [
        'id' => 'boolean',
      ],
      'sort' => [
        'id' => 'boolean',
      ],
      'filter' => [
        'id' => 'boolean',
      ],
    ],
    'warning_message' => [
      'title' => t('Warning messages'),
      'help' => t('Warning messages associated with the base carbon estimate.'),
      'field' => [
        'id' => 'standard',
      ],
    ],
  ];

  // ACCU estimate table.
  $data['farm_loocc_accu_estimate'] = [
    'table' => [
      'group' => t('LOOC-C estimate'),
    ],
    'estimate_id' => [
      'title' => t('Base estimate ID'),
      'help' => t('ID of the base LOOC-C estimate.'),
      'relationship' => [
        'base' => 'farm_loocc_estimate',
        'base_field' => 'id',
        'id' => 'standard',
        'label' => t('Base estimate'),
      ],
    ],
    'method_id' => [
      'title' => t('Method ID'),
      'help' => t('The method id for the estimate.'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'standard',
      ],
    ],
    'warning_message' => [
      'title' => t('Warning messages'),
      'help' => t('Warning messages associated with the ACCU estimate.'),
      'field' => [
        'id' => 'standard',
      ],
    ],
  ];

  // Numeric fields.
  $numeric_fields = [
    'farm_loocc_estimate' => [
      'project_length' => [
        'title' => t('Project length'),
        'help' => t('The project length of the estimate.'),
      ],
      'bd_average' => [
        'title' => t('Average percent bulk density'),
        'help' => t('The estimated average bulk density.'),
      ],
      'carbon_average' => [
        'title' => t('Average percent organic carbon'),
        'help' => t('The estimated average percent organic carbon.'),
      ],
      'carbon_target' => [
        'title' => t('Target percent organic carbon'),
        'help' => t('The target average percent organic carbon at the end of the project.'),
      ],
    ],
    'farm_loocc_accu_estimate' => [
      'annual' => [
        'title' => t('Annual ACCUs'),
        'help' => t('The estimated annual ACCUs.'),
      ],
      'project' => [
        'title' => t('Project ACCUs'),
        'help' => t('The estimated total project ACCUs.'),
      ],
    ],
  ];
  foreach ($numeric_fields as $table => $table_fields) {
    foreach ($table_fields as $field_id => $field_info) {
      // Add default attributes for numeric fields.
      $field_info += [
        'field' => [
          'id' => 'numeric',
          'click sortable' => TRUE,
        ],
        'filter' => [
          'id' => 'numeric',
        ],
        'sort' => [
          'id' => 'sort',
        ],
      ];
      $data[$table][$field_id] = $field_info;
    }
  }

  // Add a custom accu_estimates field to the farm_loocc_estimate table.
  // This uses a custom views field plugin to display a list of ACCU estimates.
  $data['farm_loocc_estimate']['accu_estimates'] = [
    'title' => t('A list of all ACCU estimates'),
    'help' => t('Displays a select list of all ACCU estimates.'),
    'field' => [
      'id' => 'farm_loocc_accu_estimates',
    ],
  ];

  // Add a custom average_lrf_rating field to the farm_loocc_estimate table.
  // This uses a custom views field plugin to display the average LRF rating.
  $data['farm_loocc_estimate']['average_lrf_rating'] = [
    'title' => t('Average LRF rating'),
    'help' => t('The average LRF rating across ERF estimates.'),
    'field' => [
      'id' => 'farm_loocc_average_lrf_rating',
      'click sortable' => TRUE,
    ],
  ];

  // Add a relationship from the farm_loocc_estimate base table to the
  // farm_loocc_accu_estimate table. This allows a view of base estimates
  // to add a relationship to specific ACCU Estimate by method_id.
  // See the ACCUEstimate views relationship.
  $data['farm_loocc_estimate']['accu_estimate'] = [
    'title' => t('ACCU estimate'),
    'help' => t('An ACCU estimate.'),
    'relationship' => [
      'label' => t('ACCU estimate'),
      'id' => 'accu_estimate',
      'base' => 'farm_loocc_accu_estimate',
      'base field' => 'estimate_id',
      'field' => 'id',
    ],
  ];

  // Add a relationship to the asset_data table that references the
  // farm_loocc_estimate table.
  $data['asset_field_data']['farm_loocc_estimate'] = [
    'title' => t('LOOC-C estimate'),
    'help' => t('LOOC-C estimates associated with an asset.'),
    'relationship' => [
      'base' => 'farm_loocc_estimate',
      'base field' => 'asset_id',
      'field' => 'id',
      'id' => 'standard',
      'label' => t('LOOC-C estimate'),
    ],
  ];

  return $data;
}
