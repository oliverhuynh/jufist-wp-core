<?php

/**
 * @file
 */

function data_table_post($vars) {
    $postData = $vars['postData'] ?? [];

    // Convert post data 
    if (isset($postData['search']['value'])) {
      $postData['search'] = $postData['search']['value'];
    }

    // Merge with parameters
    $postData = ($vars['parameters'] ?? []) + $postData;

    $start = isset($postData['start']) ? intval($postData['start']) : 0;
    $length = isset($postData['length']) ? intval($postData['length']) : 10;

    if ($length <= -1) {
      $length = 50;
    }

    if ($length >= 100) {
      $length = 100;
    }
    return [
      'postData' => $postData,
      'start' => $start,
      'length' => $length
    ];
}

function db_like($string) {
  return addcslashes($string, '\\%_');
}

function ol_op_between($item, $values, $placeholder, $or_and, $data) {
  $value1 = isset($data[$item['key'] . '_less']) && ($data[$item['key'] . '_less'] !== '') ? $data[$item['key'] . '_less'] : -1;
  $value2 = isset($data[$item['key'] . '_great'])  && ($data[$item['key'] . '_great'] !== '')? $data[$item['key'] . '_great'] : -1;
  $values = [$value1, $value2];
  if (in_array(-1, [$value1, $value2])) {
    $values = -1;
  }
  
  return [' BETWEEN ', $values, "$placeholder AND $placeholder", ['single'], $or_and];
}

function ol_op_great($item, $values, $placeholder, $or_and, $data) {
  $values = isset($data[$item['key'] . '_great']) && ($data[$item['key'] . '_great'] !== '') ? $data[$item['key'] . '_great'] : -1;
  return [' >= ', $values, $placeholder, $values, $or_and];
}

function ol_op_less($item, $values, $placeholder, $or_and, $data) {
  $values = isset($data[$item['key'] . '_less']) && ($data[$item['key'] . '_less'] !== '') ? $data[$item['key'] . '_less'] : -1;
  return [' <= ', $values, $placeholder, $values, $or_and];
}

function ol_op_not_regular_expression($item, $values, $placeholder, $or_and) {
  return ['NOT RLIKE', $values, $placeholder, $values, $or_and];
}

function ol_op_regular_expression($item, $values, $placeholder, $or_and) {
  return ['RLIKE', $values, $placeholder, $values, $or_and];
}

function ol_op_allwords($item, $values, $placeholder, $or_and) {
  return ol_op_word($item, $values, $placeholder, 'AND');
}

function ol_op_word($item, $values, $placeholder, $or_and) {
  $newvalues = [];
  preg_match_all('/ (-?)("[^"]+"|[^" ]+)/i', ' ' . $values, $matches, PREG_SET_ORDER);
  foreach ($matches as $match) {
    $phrase = FALSE;
    // Strip off phrase quotes
    if ($match[2][0] == '"') {
      $match[2] = substr($match[2], 1, -1);
      $phrase = TRUE;
    }
    $words = trim($match[2], ',?!();:-');
    $words = $phrase ? array($words) : preg_split('/ /', $words, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($words as $word) {
      $newvalues[] = '%' . db_like(trim($word, " ,!?")) . '%';
    }
  }

  return ['LIKE', $newvalues, $placeholder, $newvalues, $or_and];
}

function ol_op_not($item, $values, $placeholder, $or_and) {
  return ['NOT LIKE', '%' . db_like($values) . '%', $placeholder, $values, $or_and];
}

function ol_op_contains($item, $values, $placeholder, $or_and) {
  $values = is_array($values) ? $values : [$values];
  $values = array_map(function($value) {
    return '%' . db_like($value) . '%';
  }, $values);
  return ['LIKE', $values, $placeholder, $values, $or_and];
}

function ol_op_in($item, $values, $placeholder, $or_and) {
  $values = is_array($values) ? $values : array_map('trim', explode(',', $values));
  $values = array_filter($values);
  if (empty($values)) {
    $values = -1;
  }

  $clause = $placeholder;
  if (is_array($values)) {
    $clause = '(' . implode(',', array_fill(0, count($values), $placeholder)) . ')';
  }
  $where_values = ['single'];
  return ['IN', $values, $clause, $where_values, $or_and];
}

function build_sort($postData, $sort_columns, $priority_sort = '') {
    $first_col = $sort_columns[0];
    $sort_order = isset($postData['order']) ? $postData['order'] : [];
    $sort = implode(', ', array_filter(array_merge([$priority_sort], array_map(function ($order) use ($sort_columns, $first_col) {
        $dir = isset($order['dir']) ? strtoupper($order['dir']) : 'ASC';
        $col_idx = isset($order['column']) ? $order['column'] : 0;
        $col_name = $sort_columns[$col_idx] ?? $first_col; // Default to first column if index not found
        return "$col_name $dir";
    }, $sort_order))));

    $sort = $sort ? "ORDER BY $sort" : "";
    return $sort;
}

function build_where($data, $search_def, $placeholder = '?') {
    $where = [];
    $args = [];
    $op_aliases = [
      'LIKE' => 'contains',
      'IN' => 'in'
    ];
    foreach ($search_def as $item) {
      if (isset($item['op_auto_key'])) {
        $item['op'] = isset($data[$item['op_auto_key']]) ? $data[$item['op_auto_key']] : 'LIKE';
        if (!isset($data[$item['op_auto_key']])) {
          // Disable force because there is no auto_key
          unset($item['force']);
        }
      }

      $value_type = $item['value_type'] ?? '';

      // Any handling, reset input
      if (isset($data[$item['key']]) && $data[$item['key']] == '_') {
        unset($data[$item['key']]);
      }

      if (isset($data[$item['key']]) || ($item['force'] ?? FALSE)) {
        // Build op, values
        $op = $item['op'] ?? 'LIKE';

        $orig_value = $data[$item['key']] ?? FALSE;
        $values = $orig_value;
        if (isset($item['op_auto_in_value']) && $orig_value) {
          list ($op, $orig_value) = explode("|", $orig_value . "|");
        }

        $op = isset($op_aliases[$op]) ? $op_aliases[$op] : $op;
        $callback = 'ol_op_' . $op;
        if (function_exists($callback)) {
          list ($op, $values, $clause, $where_values, $or_and) = $callback($item, $orig_value, $placeholder, 'OR', $data);
          if ($values === -1) {
            continue ;
          }
        }

        // Make sure values is array for args
        $values = is_array($values) ? $values : [$values];
        $where_values = is_array($where_values) ? $where_values : [$where_values];

        // Build inner_where
        $inner_vwhere = [];
        foreach ($item['dbkey'] as $key) {
          $inner_where = [];
          foreach ($where_values as $value) {
            $inner_where[] = "${key} ${op} ${clause}";
          }
          $inner_vwhere[] = '( ' . join(" $or_and ", $inner_where) . ' )';
        }

        // Add args
        foreach ($item['dbkey'] as $key) {
          foreach ($values as $value) {
            $args[] = $value;
          }
        }
        $where[] = '( ' . join(" OR ", $inner_vwhere) . ' )';
      }
    }
    if ($_GET['debug_where'] ?? FALSE) {
      print_r([__FUNCTION__, $where, $args]);
    }
    return [$where, $args];
}

