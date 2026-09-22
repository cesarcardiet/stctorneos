<style>
    @page {
        size: A4 portrait;
        margin: 7mm 6mm;
    }

    * { box-sizing: border-box; }

    body.planilla-doc {
        margin: 0;
        color: #111;
        font-family: Arial, Helvetica, sans-serif;
        font-size: 9.5px;
        line-height: 1.12;
        background: #fff;
    }

    .planilla-page { page-break-after: always; }
    .planilla-page:last-child { page-break-after: auto; }

    .planilla-sheet {
        width: 100%;
        max-width: 198mm;
        margin: 0 auto;
    }

    .planilla-meta {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 3px;
        font-size: 10px;
    }
    .planilla-meta td { padding: 1px 3px; vertical-align: baseline; }
    .planilla-meta .label { font-weight: 700; white-space: nowrap; width: 1%; padding-right: 2px; }
    .planilla-meta .value { border-bottom: 1px solid #222; min-width: 40px; }
    .planilla-meta .spacer { width: 8px; border: 0; }

    .planilla-scoreline {
        display: grid;
        grid-template-columns: 1fr 36px 18px 36px 1fr;
        align-items: center;
        gap: 6px;
        margin: 5px 0 4px;
        padding: 5px 8px;
        background: #d9d9d9;
        border: 1px solid #888;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
    }
    .planilla-scoreline .team-home { text-align: right; }
    .planilla-scoreline .team-away { text-align: left; }
    .planilla-scoreline .score-box {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 32px;
        height: 24px;
        border: 1.5px solid #111;
        background: #fff;
        font-size: 15px;
        font-weight: 800;
    }
    .planilla-scoreline .score-sep { text-align: center; font-size: 13px; }

    .planilla-site-row {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 10px;
        align-items: start;
        margin-bottom: 2px;
    }
    .planilla-site-meta { width: 100%; border-collapse: collapse; font-size: 10px; }
    .planilla-site-meta td { padding: 1px 3px; vertical-align: baseline; }
    .planilla-site-meta .label { font-weight: 700; white-space: nowrap; width: 1%; }
    .planilla-site-meta .value { border-bottom: 1px solid #222; }

    .planilla-periods {
        border-collapse: collapse;
        font-size: 8.5px;
        min-width: 122px;
    }
    .planilla-periods th,
    .planilla-periods td {
        border: 1px solid #111;
        padding: 2px 8px;
        text-align: center;
        height: 15px;
        background: #fff;
    }
    .planilla-periods th:first-child {
        text-align: left;
        font-weight: 700;
        white-space: nowrap;
        background: #eee;
    }

    .planilla-ref {
        margin: 3px 0 8px;
        font-size: 10px;
        display: flex;
        align-items: baseline;
        gap: 4px;
    }
    .planilla-ref .label { font-weight: 700; }
    .planilla-ref .line {
        flex: 1;
        border-bottom: 1px solid #222;
        min-height: 14px;
    }

    .planilla-team-block {
        margin-bottom: 8px;
        page-break-inside: avoid;
    }

    .planilla-team-grid {
        display: grid;
        grid-template-columns: 1fr 148px;
        gap: 5px;
        align-items: start;
    }

    .planilla-team-title {
        margin: 0;
        padding: 3px 6px;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        background: #d0d0d0;
        border: 1px solid #888;
    }

    .planilla-roster {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }
    .planilla-roster th,
    .planilla-roster td {
        border: 1px solid #111;
        padding: 0 3px;
        height: 13px;
        vertical-align: middle;
    }
    .planilla-roster thead th {
        background: #cfcfcf;
        font-weight: 700;
        text-align: center;
        font-size: 9px;
        height: 15px;
    }
    .planilla-roster thead th.col-player { text-align: left; padding-left: 4px; }
    .planilla-roster .col-idx {
        width: 12px;
        max-width: 12px;
        text-align: center;
        font-weight: 700;
        font-size: 8px;
        padding: 0;
    }
    .planilla-roster thead th.col-idx {
        width: 12px;
        max-width: 12px;
        padding: 0;
        background: #cfcfcf;
    }
    .planilla-roster .col-player {
        font-size: 8.5px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        padding-left: 3px;
    }
    .planilla-roster .col-doc {
        width: 58px;
        font-size: 7.5px;
        text-align: center;
        white-space: nowrap;
        padding: 0 2px;
    }
    .planilla-roster thead th.col-doc {
        font-size: 8px;
    }
    /* Espacios para número de camiseta y tarjetas (sin línea decorativa) */
    .planilla-roster .col-no {
        width: 18px;
        max-width: 18px;
        text-align: center;
        font-size: 8px;
        padding: 0 1px;
    }
    .planilla-roster .col-card {
        width: 22px;
        max-width: 22px;
        text-align: center;
        font-size: 8px;
        padding: 0 1px;
        background: #fff;
    }
    .planilla-roster .col-obs {
        width: 52px;
        max-width: 52px;
        font-size: 7px;
        padding: 0 2px;
        background: #fff;
    }
    .planilla-roster thead th.col-obs {
        font-size: 8px;
    }

    .planilla-side { display: flex; flex-direction: column; gap: 4px; }

    .planilla-goals-title {
        margin: 0;
        padding: 3px 4px;
        text-align: center;
        font-weight: 800;
        font-size: 10px;
        text-transform: uppercase;
        background: #cfcfcf;
        border: 1px solid #888;
    }

    .planilla-goals-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 2px;
        border: 1px solid #111;
        padding: 3px;
        background: #fff;
    }
    .planilla-goal-cell {
        position: relative;
        border: 1px solid #999;
        min-height: 22px;
        padding: 1px 1px 1px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: space-between;
    }
    .planilla-goal-num {
        align-self: flex-start;
        font-size: 6.5px;
        font-weight: 700;
        line-height: 1;
    }
    .planilla-goal-mark {
        font-size: 9px;
        font-weight: 800;
        line-height: 1;
        min-height: 9px;
    }
    .planilla-goal-slot {
        font-size: 8px;
        color: #555;
        line-height: 1;
    }

    .planilla-subs {
        width: 100%;
        border-collapse: collapse;
        font-size: 8px;
    }
    .planilla-subs th,
    .planilla-subs td {
        border: 1px solid #111;
        padding: 1px 2px;
        text-align: center;
        height: 14px;
    }
    .planilla-subs thead th { background: #cfcfcf; font-weight: 700; }
    .planilla-subs th.row-label {
        text-align: left;
        white-space: nowrap;
        width: 46px;
        font-weight: 700;
        background: #eee;
    }

    .planilla-footer-lines { margin-top: 4px; font-size: 9px; }
    .planilla-footer-lines .staff-row {
        display: flex;
        align-items: baseline;
        gap: 4px;
        margin-bottom: 4px;
    }
    .planilla-footer-lines strong { white-space: nowrap; }
    .planilla-footer-lines .staff-row span {
        flex: 1;
        border-bottom: 1px solid #111;
        min-height: 13px;
    }

    .planilla-sanciones {
        width: 100%;
        border-collapse: collapse;
        margin-top: 4px;
        font-size: 7.5px;
        table-layout: fixed;
    }
    .planilla-sanciones .san-label {
        text-align: left;
        font-weight: 700;
        white-space: nowrap;
        width: 58px;
        padding: 1px 2px;
        vertical-align: middle;
    }
    .planilla-sanciones .san-blank {
        border: 0;
        height: 12px;
        padding: 0;
    }
    .planilla-sanciones .san-box {
        border: 1px solid #111;
        height: 13px;
        width: 14px;
        padding: 0;
        background: #fff;
    }
    .planilla-sanciones .san-dots {
        font-weight: 400;
        letter-spacing: 1px;
        margin-left: 2px;
        color: #333;
    }

    .planilla-actions {
        position: sticky;
        bottom: 0;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        justify-content: center;
        padding: 12px;
        background: rgba(255, 255, 255, .96);
        border-top: 1px solid #ddd;
    }
    .planilla-actions button,
    .planilla-actions a {
        border: 0;
        border-radius: 8px;
        padding: 10px 16px;
        font-weight: 700;
        text-decoration: none;
        color: #fff;
        background: #056bff;
        cursor: pointer;
        font-size: 13px;
    }
    .planilla-actions a.ghost { background: #eef4ff; color: #056bff; }

    .planilla-picker-filters {
        display: flex;
        flex-wrap: wrap;
        gap: .75rem;
        align-items: end;
        margin-bottom: 1rem;
    }
    .planilla-picker-filters label {
        display: grid;
        gap: .25rem;
        font-size: .85rem;
    }
    .planilla-picker-table { width: 100%; border-collapse: collapse; }
    .planilla-picker-table th,
    .planilla-picker-table td {
        padding: .55rem .45rem;
        border-bottom: 1px solid rgba(0, 213, 255, .12);
        text-align: left;
        font-size: .88rem;
    }
    .planilla-picker-table th {
        color: var(--stc-muted);
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .04em;
    }
    .planilla-picker-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .65rem;
        margin-top: 1rem;
    }

    @media screen {
        body.planilla-doc {
            background: #e8e8e8;
            padding: 16px 0 80px;
        }
        .planilla-sheet,
        .planilla-page .planilla-sheet {
            background: #fff;
            padding: 8mm 7mm;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .12);
        }
    }

    @media print {
        .no-print { display: none !important; }
        body.planilla-doc { background: #fff; padding: 0; }
        .planilla-sheet {
            max-width: none;
            box-shadow: none;
            padding: 0;
        }
    }
</style>
