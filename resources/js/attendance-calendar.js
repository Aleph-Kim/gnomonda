const WEEKDAYS = ['일', '월', '화', '수', '목', '금', '토'];
const TYPE_LABELS = { check_in: '출근', check_out: '퇴근' };

const today = new Date();

const state = {
    year: today.getFullYear(),
    month: today.getMonth() + 1, // 1-12
    records: [],
    selectedDate: null,
};

const root = document.getElementById('attendance-calendar-root');

async function fetchRecords(year, month) {
    const res = await fetch(`/api/attendance-records?year=${year}&month=${month}`, {
        headers: { Accept: 'application/json' },
    });

    if (!res.ok) throw new Error('출퇴근 기록을 불러오지 못했습니다.');

    const json = await res.json();

    return json.data;
}

async function saveRecord(date, type, time) {
    const res = await fetch('/api/attendance-records', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ date, type, time }),
    });

    if (!res.ok) throw new Error('저장에 실패했습니다.');
}

async function deleteRecord(id) {
    const res = await fetch(`/api/attendance-records/${id}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json' },
    });

    if (!res.ok) throw new Error('삭제에 실패했습니다.');
}

async function loadMonth() {
    state.records = await fetchRecords(state.year, state.month);
    render();
}

function recordFor(date, type) {
    return state.records.find((r) => r.date === date && r.type === type) ?? null;
}

function changeMonth(delta) {
    let month = state.month + delta;
    let year = state.year;

    if (month < 1) {
        month = 12;
        year -= 1;
    } else if (month > 12) {
        month = 1;
        year += 1;
    }

    state.year = year;
    state.month = month;
    state.selectedDate = null;
    loadMonth();
}

function selectDate(date) {
    state.selectedDate = state.selectedDate === date ? null : date;
    render();
}

function dateString(day) {
    return `${state.year}-${String(state.month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

function renderCalendarGrid() {
    const firstWeekday = new Date(state.year, state.month - 1, 1).getDay();
    const daysInMonth = new Date(state.year, state.month, 0).getDate();

    const cells = [];

    for (let i = 0; i < firstWeekday; i++) {
        cells.push('<div></div>');
    }

    for (let day = 1; day <= daysInMonth; day++) {
        const date = dateString(day);
        const checkIn = recordFor(date, 'check_in');
        const checkOut = recordFor(date, 'check_out');
        const isSelected = state.selectedDate === date;

        cells.push(`
            <button
                type="button"
                data-date="${date}"
                class="flex flex-col items-start gap-1 rounded-md border p-2 text-left text-sm hover:bg-gray-100 ${isSelected ? 'border-blue-500 bg-blue-50' : 'border-gray-200'}"
            >
                <span class="font-medium">${day}</span>
                ${checkIn ? `<span class="text-xs text-emerald-600">출근 ${checkIn.time}</span>` : ''}
                ${checkOut ? `<span class="text-xs text-rose-600">퇴근 ${checkOut.time}</span>` : ''}
            </button>
        `);
    }

    return cells.join('');
}

function renderDatePanel() {
    if (!state.selectedDate) return '';

    const rows = ['check_in', 'check_out']
        .map((type) => {
            const record = recordFor(state.selectedDate, type);

            return `
                <div class="flex items-center gap-2">
                    <span class="w-12 text-sm text-gray-600">${TYPE_LABELS[type]}</span>
                    <input
                        type="time"
                        data-type="${type}"
                        class="time-input rounded-md border border-gray-300 px-2 py-1 text-sm"
                        value="${record ? record.time : ''}"
                    >
                    <button type="button" data-action="save" data-type="${type}" class="rounded-md bg-blue-600 px-3 py-1 text-sm text-white hover:bg-blue-500">저장</button>
                    ${record ? `<button type="button" data-action="delete" data-id="${record.id}" class="rounded-md border border-gray-300 px-3 py-1 text-sm hover:bg-gray-100">삭제</button>` : ''}
                </div>
            `;
        })
        .join('');

    return `
        <div class="mt-6 rounded-md border border-gray-200 p-4">
            <p class="mb-3 font-medium">${state.selectedDate}</p>
            <div class="flex flex-col gap-3">${rows}</div>
        </div>
    `;
}

function render() {
    root.innerHTML = `
        <div class="flex items-center justify-between">
            <button type="button" data-action="prev-month" class="rounded-md border border-gray-300 px-3 py-1 text-sm hover:bg-gray-100">이전</button>
            <h1 class="text-lg font-semibold">${state.year}년 ${state.month}월</h1>
            <button type="button" data-action="next-month" class="rounded-md border border-gray-300 px-3 py-1 text-sm hover:bg-gray-100">다음</button>
        </div>
        <div class="mt-4 grid grid-cols-7 gap-2 text-center text-xs text-gray-500">
            ${WEEKDAYS.map((w) => `<div>${w}</div>`).join('')}
        </div>
        <div class="mt-1 grid grid-cols-7 gap-2">
            ${renderCalendarGrid()}
        </div>
        ${renderDatePanel()}
    `;

    root.querySelector('[data-action="prev-month"]').addEventListener('click', () => changeMonth(-1));
    root.querySelector('[data-action="next-month"]').addEventListener('click', () => changeMonth(1));

    root.querySelectorAll('[data-date]').forEach((el) => {
        el.addEventListener('click', () => selectDate(el.dataset.date));
    });

    root.querySelectorAll('[data-action="save"]').forEach((el) => {
        el.addEventListener('click', async () => {
            const type = el.dataset.type;
            const input = root.querySelector(`.time-input[data-type="${type}"]`);

            if (!input.value) return;

            await saveRecord(state.selectedDate, type, input.value);
            await loadMonth();
        });
    });

    root.querySelectorAll('[data-action="delete"]').forEach((el) => {
        el.addEventListener('click', async () => {
            await deleteRecord(el.dataset.id);
            await loadMonth();
        });
    });
}

loadMonth();
