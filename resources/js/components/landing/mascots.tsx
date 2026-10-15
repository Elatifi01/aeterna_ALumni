import type { SVGAttributes } from 'react';

/**
 * Friendly illustrations for the landing page, drawn in the Aeterna palette.
 */

const NAVY = '#0c1e33';
const BLUE = '#638fe3';
const SKY = '#90b6f7';
const CYAN = '#5ccfe6';
const LILAC = '#b9a6f5';
const MINT = '#5fd3a6';

type Props = SVGAttributes<SVGElement>;

/** Two eyes and a smile, centred on (cx, cy). */
function Face({
    cx,
    cy,
    size = 1,
    color = NAVY,
}: {
    cx: number;
    cy: number;
    size?: number;
    color?: string;
}) {
    const eye = 2.2 * size;
    const gap = 5 * size;

    return (
        <g fill={color} stroke={color}>
            <circle cx={cx - gap} cy={cy - 2 * size} r={eye} stroke="none" />
            <circle cx={cx + gap} cy={cy - 2 * size} r={eye} stroke="none" />
            <path
                d={`M${cx - 4.5 * size} ${cy + 3 * size} q${4.5 * size} ${4.5 * size} ${9 * size} 0`}
                fill="none"
                strokeWidth={2 * size}
                strokeLinecap="round"
            />
        </g>
    );
}

export function CursorMascot(props: Props) {
    return (
        <svg viewBox="0 0 180 180" fill="none" aria-hidden {...props}>
            <path
                d="M38 22c-4-3-10 0-9 6l20 112c1 6 9 7 12 2l17-30 30 42c3 4 9 5 13 2l9-7c4-3 4-9 1-12l-30-41 33-9c6-2 6-10 1-13Z"
                fill={NAVY}
            />
            <circle cx="70" cy="62" r="5" fill="#fff" />
            <circle cx="88" cy="58" r="5" fill="#fff" />
            <path
                d="M66 76c8 10 22 8 28-3"
                stroke="#fff"
                strokeWidth="6"
                strokeLinecap="round"
            />
            <circle cx="112" cy="30" r="22" fill={SKY} />
            <Face cx={112} cy={30} size={0.9} />
            <circle cx="22" cy="72" r="16" fill={MINT} />
            <Face cx={22} cy={72} size={0.7} />
            <circle cx="146" cy="120" r="25" fill={LILAC} />
            <Face cx={146} cy={120} size={1} />
        </svg>
    );
}

export function FolderMascot(props: Props) {
    return (
        <svg viewBox="0 0 120 120" fill="none" aria-hidden {...props}>
            <rect x="28" y="16" width="60" height="88" rx="8" fill={BLUE} />
            <rect x="86" y="22" width="10" height="18" rx="3" fill={LILAC} />
            <rect x="86" y="42" width="10" height="18" rx="3" fill={SKY} />
            <rect x="86" y="62" width="10" height="18" rx="3" fill={CYAN} />
            <rect x="86" y="82" width="10" height="16" rx="3" fill={MINT} />
            <rect x="28" y="16" width="10" height="88" rx="4" fill="#4e7fdc" />
            <Face cx={62} cy={58} size={1.1} />
        </svg>
    );
}

export function TicketMascot(props: Props) {
    return (
        <svg viewBox="0 0 120 120" fill="none" aria-hidden {...props}>
            <g transform="rotate(-22 60 60)">
                <path
                    d="M40 12h40v20a8 8 0 0 0 0 16v60H40V48a8 8 0 0 0 0-16Z"
                    fill={SKY}
                />
                <path
                    d="M42 66h36"
                    stroke="#fff"
                    strokeWidth="2.5"
                    strokeDasharray="4 4"
                />
                <Face cx={60} cy={44} size={1} />
            </g>
            <path
                d="M70 104c6-10 18-12 26-6"
                stroke={MINT}
                strokeWidth="7"
                strokeLinecap="round"
            />
        </svg>
    );
}

export function FacesCluster(props: Props) {
    return (
        <svg viewBox="0 0 120 120" fill="none" aria-hidden {...props}>
            <circle cx="62" cy="22" r="15" fill={LILAC} />
            <Face cx={62} cy={22} size={0.7} />
            <circle cx="26" cy="58" r="15" fill={SKY} />
            <Face cx={26} cy={58} size={0.7} />
            <circle cx="98" cy="56" r="14" fill={CYAN} />
            <Face cx={98} cy={56} size={0.65} />
            <circle cx="72" cy="88" r="15" fill={MINT} />
            <Face cx={72} cy={88} size={0.7} />
            <circle cx="60" cy="56" r="21" fill={NAVY} />
            <Face cx={60} cy={56} size={1} color="#fff" />
        </svg>
    );
}

export function BriefcaseMascot(props: Props) {
    return (
        <svg viewBox="0 0 120 120" fill="none" aria-hidden {...props}>
            <path
                d="M46 30v-6a6 6 0 0 1 6-6h16a6 6 0 0 1 6 6v6"
                stroke={NAVY}
                strokeWidth="7"
            />
            <rect x="16" y="30" width="88" height="66" rx="12" fill={BLUE} />
            <rect x="16" y="54" width="88" height="8" fill="#4e7fdc" />
            <rect x="52" y="50" width="16" height="16" rx="4" fill={CYAN} />
            <Face cx={60} cy={82} size={0.9} />
            <circle cx="98" cy="28" r="13" fill={MINT} />
            <Face cx={98} cy={28} size={0.6} />
        </svg>
    );
}

export function SparkMascot(props: Props) {
    return (
        <svg viewBox="0 0 120 120" fill="none" aria-hidden {...props}>
            <path
                d="M60 10c4 24 14 34 38 38-24 4-34 14-38 38-4-24-14-34-38-38 24-4 34-14 38-38Z"
                fill={LILAC}
            />
            <Face cx={60} cy={50} size={1} />
            <path
                d="M96 78c2 10 6 14 16 16-10 2-14 6-16 16-2-10-6-14-16-16 10-2 14-6 16-16Z"
                fill={CYAN}
            />
            <circle cx="22" cy="92" r="9" fill={SKY} />
        </svg>
    );
}
