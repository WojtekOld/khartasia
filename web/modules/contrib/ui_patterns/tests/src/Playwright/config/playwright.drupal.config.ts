/*
 * Tests configuration.
 */
export default {
  operatingMode: 'native',
  drushCmd: 'drush',

  logInUrl: 'user/login',
  logOutUrl: 'user/logout',

  modules: 'admin/modules',
  performance: 'admin/config/development/performance',
  statusReport: 'admin/reports/status',

  blockLibraryUrl: 'admin/structure/block/library/{theme}',
  blockLayoutUrl: 'admin/structure/block/list/{theme}',
}
