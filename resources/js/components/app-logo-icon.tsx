import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    const loop =
        'M64 72C50 46 14 48 14 72s36 26 50 0c14-26 50-24 50 0s-36 26-50 0Z';

    return (
        <svg
            {...props}
            viewBox="0 0 128 128"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
        >
            <mask
                id="aeterna-mark-weave"
                maskUnits="userSpaceOnUse"
                x="0"
                y="0"
                width="128"
                height="128"
            >
                <rect width="128" height="128" fill="#fff" />
                <path d={loop} stroke="#000" strokeWidth="15" fill="none" />
            </mask>
            <g mask="url(#aeterna-mark-weave)" fill="currentColor">
                <path d="M64 10 118 114 96 114 64 52 32 114 10 114Z" />
                <rect x="26" y="86" width="76" height="12" rx="2" />
            </g>
            <path
                d={loop}
                stroke="currentColor"
                strokeWidth="9"
                fill="none"
                strokeLinecap="round"
            />
        </svg>
    );
}
