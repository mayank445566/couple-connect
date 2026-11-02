/** Parses "YYYY-MM-DD" (and optional "HH:MM") typed by the user in local time into an ISO string, or null if invalid. */
export function toIso(date: string, time = '00:00'): string | null {
  const dm = date.trim().match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
  const tm = (time.trim() || '00:00').match(/^(\d{1,2}):(\d{2})$/);
  if (!dm || !tm) return null;
  const d = new Date(Number(dm[1]), Number(dm[2]) - 1, Number(dm[3]), Number(tm[1]), Number(tm[2]));
  if (isNaN(d.getTime()) || d.getMonth() !== Number(dm[2]) - 1) return null;
  return d.toISOString();
}

export function splitLocal(iso: string | null | undefined): { date: string; time: string } {
  if (!iso) return { date: '', time: '' };
  const d = new Date(iso);
  const p = (n: number) => String(n).padStart(2, '0');
  return { date: `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`, time: `${p(d.getHours())}:${p(d.getMinutes())}` };
}

export function fmtDate(iso: string | null | undefined, withTime = false): string {
  if (!iso) return '';
  const d = new Date(iso);
  return withTime ? d.toLocaleString(undefined, { day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit' }) : d.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
}
