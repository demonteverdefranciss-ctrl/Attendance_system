import { useState } from 'react';

export default function useTableSort(rows, values) {
    const [sort, setSort] = useState({ key: null, descending: false });
    const sorted = !sort.key ? rows : [...rows].sort((a, b) => {
        const left = values[sort.key](a);
        const right = values[sort.key](b);
        if (left == null) return right == null ? 0 : 1;
        if (right == null) return -1;
        const result = typeof left === 'number' && typeof right === 'number'
            ? left - right : String(left).localeCompare(String(right), undefined, { numeric: true, sensitivity: 'base' });
        return sort.descending ? -result : result;
    });
    return { rows: sorted, sort, onSort: (key) => setSort({ key, descending: sort.key === key && !sort.descending }) };
}
