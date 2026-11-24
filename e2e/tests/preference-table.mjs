import { sql, tableExists } from './support.mjs';

export const TABLE = 'runio_ux_preference';
export const MOVED = 'runio_ux_preference_e2e_moved';

/** Puts the preference table back if an interrupted run of missing-table.spec.mjs left it renamed. Runs before and after every test run. */
export function restorePreferenceTable() {
    if (!tableExists(MOVED)) return;
    sql(tableExists(TABLE) ? `drop table ${MOVED}` : `rename table ${MOVED} to ${TABLE}`);
}
