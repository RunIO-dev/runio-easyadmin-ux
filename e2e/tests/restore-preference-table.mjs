import { restorePreferenceTable } from './preference-table.mjs';

export default function teardown() {
    restorePreferenceTable();
}
