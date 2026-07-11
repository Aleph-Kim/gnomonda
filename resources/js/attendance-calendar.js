const WEEKDAYS = ['일', '월', '화', '수', '목', '금', '토'];
const TYPE_LABELS = { check_in: '출근', check_out: '퇴근', meeting: '미팅' };
const TIME_FIELD = { check_in: 'check_in_time', check_out: 'check_out_time' };
const DEFAULT_TIME = { check_in: '10:00', check_out: '16:00' };
const HOURS = Array.from({ length: 24 }, (_, h) => String(h).padStart(2, '0'));
const MINUTES = Array.from({ length: 60 }, (_, m) => String(m).padStart(2, '0'));
const WHEEL_ITEM_HEIGHT = 40; // px, must match the h-10 wheel item buttons
const WHEEL_HEIGHT = 220; // px, visible wheel viewport height — must match the literal h-[220px] classes below (Tailwind can't see interpolated class names)
const WHEEL_SPACER = WHEEL_HEIGHT / 2 - WHEEL_ITEM_HEIGHT / 2; // keeps scrollTop = index * ITEM_HEIGHT centered

const ICONS = {
    chevronLeft: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5"><path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 010 1.06L9.06 10l3.73 3.71a.75.75 0 11-1.06 1.06l-4.25-4.25a.75.75 0 010-1.06l4.25-4.25a.75.75 0 011.06 0z" clip-rule="evenodd" /></svg>`,
    chevronRight: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 010-1.06L10.94 10 7.21 6.29a.75.75 0 111.06-1.06l4.25 4.25a.75.75 0 010 1.06l-4.25 4.25a.75.75 0 01-1.06 0z" clip-rule="evenodd" /></svg>`,
};

const today = new Date();

const state = {
    year: today.getFullYear(),
    month: today.getMonth() + 1, // 1-12
    records: [],
    selectedDate: null,
    openPicker: null, // null | 'check_in' | 'check_out'
    pickerSnapshot: null, // pendingTime value to restore on cancel
    pendingTime: { check_in: null, check_out: null }, // 'HH:MM' | null
    isForecast: { check_in: false, check_out: false }, // pendingTime 값이 예측값인지 여부
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

async function saveRecord(date, type, value) {
    const body = type === 'meeting' ? { date, type, meeting: value } : { date, type, time: value };

    const res = await fetch('/api/attendance-records', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(body),
    });

    if (!res.ok) throw new Error('저장에 실패했습니다.');
}

async function deleteRecord(id, type) {
    const res = await fetch(`/api/attendance-records/${id}?type=${type}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json' },
    });

    if (!res.ok) throw new Error('삭제에 실패했습니다.');
}

async function loadMonth() {
    state.records = await fetchRecords(state.year, state.month);
    render();
}

async function fetchForecast(date) {
    const res = await fetch(`/api/attendance-records/forecast?date=${date}`, {
        headers: { Accept: 'application/json' },
    });

    if (!res.ok) throw new Error('예측 시간을 불러오지 못했습니다.');

    return res.json();
}

function recordFor(date) {
    return state.records.find((r) => r.date === date) ?? null;
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
    state.openPicker = null;
    loadMonth();
}

function selectDate(date) {
    state.selectedDate = state.selectedDate === date ? null : date;
    state.openPicker = null;
    const record = recordFor(state.selectedDate);
    state.pendingTime = {
        check_in: record?.check_in_time ?? DEFAULT_TIME.check_in,
        check_out: record?.check_out_time ?? DEFAULT_TIME.check_out,
    };
    state.isForecast = { check_in: false, check_out: false };
    render();

    if (state.selectedDate) applyForecast(state.selectedDate, record);
}

async function applyForecast(date, record) {
    if (record?.check_in_time && record?.check_out_time) return; // 둘 다 이미 저장돼 있으면 예측 불필요

    let forecast;
    try {
        forecast = await fetchForecast(date);
    } catch {
        return; // 예측 실패 시 조용히 기본값 유지
    }

    if (state.selectedDate !== date) return; // 그 사이 다른 날짜로 이동한 경우 무시

    ['check_in', 'check_out'].forEach((type) => {
        const hasRecord = Boolean(record?.[TIME_FIELD[type]]);
        const predicted = forecast[TIME_FIELD[type]];

        if (!hasRecord && predicted && state.openPicker !== type) {
            state.pendingTime[type] = predicted;
            state.isForecast[type] = true;
        }
    });

    render();
}

function toggleTimePicker(type) {
    if (state.openPicker === type) {
        state.openPicker = null;
    } else {
        state.openPicker = type;
        state.pickerSnapshot = state.pendingTime[type];
    }
    render();
}

function setupWheel(container, values, initialValue, onSettle) {
    const items = container.querySelectorAll('[data-wheel-value]');
    const index = Math.max(values.indexOf(initialValue), 0);

    const updateFade = () => {
        const centerIndex = container.scrollTop / WHEEL_ITEM_HEIGHT;
        items.forEach((el, i) => {
            const distance = Math.abs(i - centerIndex);
            el.style.opacity = String(Math.max(1 - distance * 0.4, 0.15));
            el.style.transform = `scale(${Math.max(1 - distance * 0.08, 0.8)})`;
            el.style.fontWeight = distance < 0.5 ? '600' : '400';
        });
    };

    requestAnimationFrame(() => {
        container.scrollTop = index * WHEEL_ITEM_HEIGHT;
        updateFade();
    });

    let settleTimer = null;
    container.addEventListener('scroll', () => {
        updateFade();
        clearTimeout(settleTimer);
        settleTimer = setTimeout(() => {
            const centerIndex = Math.round(container.scrollTop / WHEEL_ITEM_HEIGHT);
            const clamped = Math.min(Math.max(centerIndex, 0), values.length - 1);
            onSettle(values[clamped]);
        }, 120);
    });

    items.forEach((el, i) => {
        el.addEventListener('click', () => {
            container.scrollTo({ top: i * WHEEL_ITEM_HEIGHT, behavior: 'smooth' });
        });
    });

    // Native overflow scrolling already handles touch drag; mouse pointers don't
    // get click-and-drag panning for free, so wire that up explicitly.
    let dragging = false;
    let dragStartY = 0;
    let dragStartScrollTop = 0;

    container.addEventListener('pointerdown', (e) => {
        if (e.pointerType !== 'mouse') return;
        dragging = true;
        dragStartY = e.clientY;
        dragStartScrollTop = container.scrollTop;
        container.setPointerCapture(e.pointerId);
        container.style.scrollSnapType = 'none';
    });

    container.addEventListener('pointermove', (e) => {
        if (!dragging) return;
        container.scrollTop = dragStartScrollTop - (e.clientY - dragStartY);
    });

    const endDrag = () => {
        if (!dragging) return;
        dragging = false;
        container.style.scrollSnapType = '';
        const nearest = Math.round(container.scrollTop / WHEEL_ITEM_HEIGHT);
        const clamped = Math.min(Math.max(nearest, 0), values.length - 1);
        container.scrollTo({ top: clamped * WHEEL_ITEM_HEIGHT, behavior: 'smooth' });
    };

    container.addEventListener('pointerup', endDrag);
    container.addEventListener('pointercancel', endDrag);
}

function dateString(day) {
    return `${state.year}-${String(state.month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

function todayString() {
    return `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
}

function formatDateHeader(date) {
    const [y, m, d] = date.split('-').map(Number);
    const weekday = WEEKDAYS[new Date(y, m - 1, d).getDay()];
    return `${m}월 ${d}일 ${weekday}요일`;
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
        const weekday = new Date(state.year, state.month - 1, day).getDay();
        const record = recordFor(date);
        const checkIn = record?.check_in_time ?? null;
        const checkOut = record?.check_out_time ?? null;
        const meeting = Boolean(record?.meeting);
        const isSelected = state.selectedDate === date;
        const isToday = date === todayString();

        let cellClass = 'flex min-h-[76px] flex-col items-center rounded-xl p-2 transition-colors';
        let numberClass = 'text-base leading-none';

        if (isSelected) {
            cellClass += ' bg-[#2B2B30]';
            numberClass += ' font-semibold text-white';
        } else {
            cellClass += ' hover:bg-[#F0F0F2]';
            if (isToday) cellClass += ' bg-[#F0F0F2]';
            if (weekday === 0) numberClass += ' text-[#E57373]';
            else if (weekday === 6) numberClass += ' text-[#64B5F6]';
            else numberClass += ' text-[#1A1A1A]';
            numberClass += isToday ? ' font-semibold' : ' font-normal';
        }

        let recordMark = '';
        if (checkIn || checkOut || meeting) {
            const barClass = isSelected ? 'bg-white/50' : '';
            const inColor = isSelected ? 'text-white/80' : 'text-[#3D7DFF]';
            const outColor = isSelected ? 'text-white/80' : 'text-[#E5484D]';
            const meetingDot = isSelected ? 'bg-white/50' : 'bg-[#8B5CF6]';

            recordMark = `
                <span class="flex items-center gap-1">
                    ${checkIn ? `<span class="h-[3px] w-3 rounded-full ${barClass || 'bg-[#3D7DFF]'}"></span>` : ''}
                    ${checkOut ? `<span class="h-[3px] w-3 rounded-full ${barClass || 'bg-[#E5484D]'}"></span>` : ''}
                    ${meeting ? `<span class="h-[5px] w-[5px] rounded-full ${meetingDot}"></span>` : ''}
                </span>
                <span class="flex flex-col items-center leading-tight">
                    ${checkIn ? `<span class="text-[9px] font-medium tabular-nums ${inColor}">${checkIn}</span>` : ''}
                    ${checkOut ? `<span class="text-[9px] font-medium tabular-nums ${outColor}">${checkOut}</span>` : ''}
                </span>
            `;
        }

        cells.push(`
            <button type="button" data-date="${date}" class="${cellClass}">
                <span class="${numberClass}">${day}</span>
                <span class="flex flex-1 flex-col items-center justify-center gap-1">
                    ${recordMark}
                </span>
            </button>
        `);
    }

    return cells.join('');
}

function renderWheelColumn(kind, values) {
    const items = values
        .map(
            (v) => `
                <button type="button" data-wheel-value="${v}" class="flex h-10 w-full shrink-0 snap-center items-center justify-center text-[22px] tabular-nums text-[#1A1A1A]">${v}</button>
            `
        )
        .join('');

    return `
        <div class="no-scrollbar h-[220px] w-14 cursor-grab snap-y snap-mandatory overflow-y-auto scroll-smooth active:cursor-grabbing" data-wheel="${kind}">
            <div style="height:${WHEEL_SPACER}px"></div>
            ${items}
            <div style="height:${WHEEL_SPACER}px"></div>
        </div>
    `;
}

function renderTimePicker(type) {
    const value = state.pendingTime[type];
    const isOpen = state.openPicker === type;
    const hasRecord = Boolean(recordFor(state.selectedDate)?.[TIME_FIELD[type]]);
    const isForecast = !hasRecord && state.isForecast[type];

    return `
        <div data-time-picker="${type}">
            <button
                type="button"
                data-action="toggle-time"
                data-type="${type}"
                class="-mx-2 -my-1 flex items-baseline gap-2 rounded-lg px-2 py-1 hover:bg-[#F0F0F2]"
            >
                <span class="text-[28px] font-semibold tabular-nums ${hasRecord ? 'text-[#1A1A1A]' : isForecast ? 'text-[#8B5CF6]' : 'text-[#A8A8AD]'}">${value}</span>
                ${isForecast ? '<span class="text-[11px] font-medium text-[#8B5CF6]">예측</span>' : ''}
                <span class="text-[13px] font-medium text-[#6B6B70]">수정</span>
            </button>
            ${
                isOpen
                    ? `
                <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 backdrop-blur-sm sm:items-center" data-action="close-time-backdrop">
                    <div class="w-full max-w-sm rounded-t-[24px] bg-white shadow-[0_-8px_40px_rgba(0,0,0,0.16)] sm:rounded-[20px]">
                        <div class="mx-auto mt-3 h-1 w-9 rounded-full bg-[#A8A8AD]/40 sm:hidden"></div>
                        <div class="flex items-center justify-between border-b border-[#ECECEE] px-5 py-4">
                            <button type="button" data-action="cancel-time" class="text-[15px] font-medium text-[#6B6B70]">취소</button>
                            <p class="text-[15px] font-semibold text-[#1A1A1A]">${TYPE_LABELS[type]} 시간</p>
                            <button type="button" data-action="confirm-time" class="text-[15px] font-semibold text-[#2B2B30]">완료</button>
                        </div>
                        <div class="relative h-[220px] px-8">
                            <div class="pointer-events-none absolute inset-x-8 top-1/2 h-10 -translate-y-1/2 border-y border-[#ECECEE] bg-[#F0F0F2]/60"></div>
                            <span class="pointer-events-none absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 text-[22px] text-[#A8A8AD]">:</span>
                            <div class="relative flex h-full justify-center gap-6">
                                ${renderWheelColumn('hour', HOURS)}
                                ${renderWheelColumn('minute', MINUTES)}
                            </div>
                        </div>
                        <div class="h-4"></div>
                    </div>
                </div>
            `
                    : ''
            }
        </div>
    `;
}

function renderMeetingRow() {
    const record = recordFor(state.selectedDate);
    const active = Boolean(record?.meeting);

    return `
        <div>
            <p class="mb-1 text-[13px] font-medium text-[#6B6B70]">${TYPE_LABELS.meeting}</p>
            <div class="flex items-center justify-between">
                <span class="text-[15px] text-[#1A1A1A]">${active ? '있음' : '없음'}</span>
                <button
                    type="button"
                    role="switch"
                    aria-checked="${active}"
                    data-action="toggle-meeting"
                    class="relative h-7 w-12 shrink-0 rounded-full transition-colors ${active ? 'bg-[#8B5CF6]' : 'bg-[#E0E0E3]'}"
                >
                    <span class="absolute top-0.5 h-6 w-6 rounded-full bg-white shadow transition-transform ${active ? 'translate-x-[22px]' : 'translate-x-0.5'}"></span>
                </button>
            </div>
        </div>
    `;
}

function renderDatePanel() {
    if (!state.selectedDate) return '';

    const timeRows = ['check_in', 'check_out']
        .map((type) => {
            const record = recordFor(state.selectedDate);
            const hasValue = Boolean(record?.[TIME_FIELD[type]]);

            return `
                <div class="mb-5 border-b border-[#ECECEE] pb-5">
                    <p class="mb-1 text-[13px] font-medium text-[#6B6B70]">${TYPE_LABELS[type]}</p>
                    <div class="flex flex-wrap items-center justify-between gap-y-2 gap-x-3">
                        ${renderTimePicker(type)}
                        <div class="flex shrink-0 items-center gap-4">
                            <button type="button" data-action="save" data-type="${type}" class="rounded-xl bg-[#2B2B30] px-5 py-2.5 text-[15px] font-semibold text-white transition hover:bg-black active:scale-[.98]">저장</button>
                            ${hasValue ? `<button type="button" data-action="delete" data-id="${record.id}" data-type="${type}" class="text-[15px] font-medium text-[#E5484D]">삭제</button>` : ''}
                        </div>
                    </div>
                </div>
            `;
        })
        .join('');

    return `
        <div class="mt-8 border-t border-[#ECECEE] pt-6">
            <p class="mb-6 text-[15px] font-semibold text-[#1A1A1A]">${formatDateHeader(state.selectedDate)}</p>
            ${timeRows}
            ${renderMeetingRow()}
        </div>
    `;
}

function render() {
    root.innerHTML = `
        <div class="rounded-[20px] bg-white p-6 shadow-[0_1px_3px_rgba(0,0,0,0.06),0_8px_24px_-12px_rgba(0,0,0,0.12)] sm:p-8">
            <div class="flex items-center justify-between">
                <h1 class="text-[22px] font-semibold text-[#1A1A1A]">${state.year}년 ${state.month}월</h1>
                <div class="flex items-center gap-1">
                    <button type="button" data-action="prev-month" class="flex h-9 w-9 items-center justify-center rounded-lg text-[#6B6B70] hover:bg-[#F0F0F2] hover:text-[#1A1A1A]" aria-label="이전 달">
                        ${ICONS.chevronLeft}
                    </button>
                    <button type="button" data-action="next-month" class="flex h-9 w-9 items-center justify-center rounded-lg text-[#6B6B70] hover:bg-[#F0F0F2] hover:text-[#1A1A1A]" aria-label="다음 달">
                        ${ICONS.chevronRight}
                    </button>
                </div>
            </div>
            <div class="mt-8 grid grid-cols-7 gap-1 border-b border-[#ECECEE] pb-2 text-center text-xs font-medium text-[#A8A8AD]">
                ${WEEKDAYS.map((w, i) => `<div class="${i === 0 ? 'text-[#E57373]' : i === 6 ? 'text-[#64B5F6]' : ''}">${w}</div>`).join('')}
            </div>
            <div class="mt-2 grid grid-cols-7 gap-1">
                ${renderCalendarGrid()}
            </div>
            ${renderDatePanel()}
        </div>
    `;

    root.querySelector('[data-action="prev-month"]').addEventListener('click', () => changeMonth(-1));
    root.querySelector('[data-action="next-month"]').addEventListener('click', () => changeMonth(1));

    root.querySelectorAll('[data-date]').forEach((el) => {
        el.addEventListener('click', () => selectDate(el.dataset.date));
    });

    root.querySelectorAll('[data-action="toggle-time"]').forEach((el) => {
        el.addEventListener('click', () => toggleTimePicker(el.dataset.type));
    });

    root.querySelectorAll('[data-action="confirm-time"]').forEach((el) => {
        el.addEventListener('click', () => {
            state.openPicker = null;
            render();
        });
    });

    root.querySelectorAll('[data-action="cancel-time"]').forEach((el) => {
        el.addEventListener('click', () => {
            state.pendingTime[state.openPicker] = state.pickerSnapshot;
            state.openPicker = null;
            render();
        });
    });

    root.querySelectorAll('[data-action="close-time-backdrop"]').forEach((el) => {
        el.addEventListener('click', (e) => {
            if (e.target !== el) return;
            state.pendingTime[state.openPicker] = state.pickerSnapshot;
            state.openPicker = null;
            render();
        });
    });

    root.querySelectorAll('[data-action="save"]').forEach((el) => {
        el.addEventListener('click', async () => {
            const type = el.dataset.type;
            const time = state.pendingTime[type];

            if (!time) return;

            await saveRecord(state.selectedDate, type, time);
            await loadMonth();
        });
    });

    root.querySelectorAll('[data-action="delete"]').forEach((el) => {
        el.addEventListener('click', async () => {
            const type = el.dataset.type;
            await deleteRecord(el.dataset.id, type);
            state.pendingTime[type] = DEFAULT_TIME[type];
            await loadMonth();
        });
    });

    root.querySelectorAll('[data-action="toggle-meeting"]').forEach((el) => {
        el.addEventListener('click', async () => {
            const record = recordFor(state.selectedDate);
            const next = !record?.meeting;
            await saveRecord(state.selectedDate, 'meeting', next);
            await loadMonth();
        });
    });

    if (state.openPicker) {
        const type = state.openPicker;
        const [hour, minute] = state.pendingTime[type].split(':');
        const picker = root.querySelector(`[data-time-picker="${type}"]`);

        setupWheel(picker.querySelector('[data-wheel="hour"]'), HOURS, hour, (newHour) => {
            state.pendingTime[type] = `${newHour}:${state.pendingTime[type].split(':')[1]}`;
        });
        setupWheel(picker.querySelector('[data-wheel="minute"]'), MINUTES, minute, (newMinute) => {
            state.pendingTime[type] = `${state.pendingTime[type].split(':')[0]}:${newMinute}`;
        });
    }
}

loadMonth();
