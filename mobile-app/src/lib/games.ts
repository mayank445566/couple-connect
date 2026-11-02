export type Pack = { id: number; title: string; level: string | null; premium: boolean; custom: boolean; questions: number; locked: boolean };
export type Game = { slug: string; title: string; description: string; section: 'free' | 'adult'; mode: 'prompt' | 'match' | 'guess' | 'quiz'; icon: string; premium: boolean; locked: boolean; rounds: number; both_answer: boolean; choose_kind: boolean; packs: Pack[] };
export type AdultStatus = { available: boolean; my_opt_in: boolean; partner_opt_in: boolean | null; premium: boolean };
export type GamesIndex = { games: { free: Game[]; adult: Game[] }; premium: boolean; adult: AdultStatus };

export type Round = {
  id: number;
  round_no: number;
  status: 'open' | 'revealed' | 'skipped';
  question: { id: number; kind: string; text: string; options: string[] | null; pick_partner: boolean };
  subject_id: number | null;
  your_turn: boolean;
  my_answer: any;
  partner_answered: boolean;
  partner_answer: any;
  result: any;
};

export type Session = {
  id: number;
  game: { slug: string; title: string; section: 'free' | 'adult'; mode: Game['mode'] };
  status: 'invited' | 'active' | 'finished' | 'stopped' | 'declined';
  started_by: number;
  level: string | null;
  round_no: number;
  total_rounds: number;
  scores: Record<string, number> | null;
  round: Round | null;
  can_skip: boolean;
  can_stop: boolean;
  summary?: { rounds_played: number; rounds_skipped: number; compatibility_percent?: number; top_category?: Record<string, string> };
};

export const FREE_LEVELS = ['easy', 'medium', 'hard'];
export const ADULT_LEVELS = ['flirty', 'romantic', 'bold', 'intimate', 'spicy'];
