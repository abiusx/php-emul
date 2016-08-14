<?php
function callback_requiring_functions()
{
  static $x=array (
    'preg_replace_callback' =>
    array (
      1 => 'callback',
    ),
    'ldap_set_rebind_proc' =>
    array (
      1 => 'callback',
    ),
    'mb_ereg_replace_callback' =>
    array (
      1 => 'callback',
    ),
    'readline_completion_function' =>
    array (
      0 => 'funcname',
    ),
    'readline_callback_handler_install' =>
    array (
      1 => 'callback',
    ),
    'header_register_callback' =>
    array (
      0 => 'callback',
    ),
    'array_walk' =>
    array (
      1 => 'funcname',
    ),
    'array_walk_recursive' =>
    array (
      1 => 'funcname',
    ),
    'array_reduce' =>
    array (
      1 => 'callback',
    ),
    'array_intersect_ukey' =>
    array (
      2 => 'callback_key_compare_func',
    ),
    'array_uintersect' =>
    array (
      2 => 'callback_data_compare_func',
    ),
    'array_uintersect_assoc' =>
    array (
      2 => 'callback_data_compare_func',
    ),
    'array_intersect_uassoc' =>
    array (
      2 => 'callback_key_compare_func',
    ),
    'array_uintersect_uassoc' =>
    array (
      2 => 'callback_data_compare_func',
      3 => 'callback_key_compare_func',
    ),
    'array_diff_ukey' =>
    array (
      2 => 'callback_key_comp_func',
    ),
    'array_udiff' =>
    array (
      2 => 'callback_data_comp_func',
    ),
    'array_udiff_assoc' =>
    array (
      2 => 'callback_key_comp_func',
    ),
    'array_diff_uassoc' =>
    array (
      2 => 'callback_data_comp_func',
    ),
    'array_udiff_uassoc' =>
    array (
      2 => 'callback_data_comp_func',
      3 => 'callback_key_comp_func',
    ),
    'array_filter' =>
    array (
      1 => 'callback',
    ),
    'array_map' =>
    array (
      0 => 'callback',
    ),
    'usort' =>
    array (
      1 => 'value_compare_func',
    ),
    'session_set_save_handler' =>
    array (
      0 => 'open',
      1 => 'close',
      2 => 'read',
      3 => 'write',
      4 => 'destroy',
      5 => 'gc',
      6 => 'create_sid',
    ),
  );
  return $x;
}