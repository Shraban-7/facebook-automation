import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 32 32"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
        >
            <path
                d="M16 4.5C9.64873 4.5 4.5 9.64873 4.5 16C4.5 22.3513 9.64873 27.5 16 27.5C22.3513 27.5 27.5 22.3513 27.5 16"
                fill="none"
                stroke="currentColor"
                strokeWidth="2.5"
                strokeLinecap="round"
            />
            <path
                d="M16 9.5C12.4101 9.5 9.5 12.4101 9.5 16C9.5 19.5899 12.4101 22.5 16 22.5C19.5899 22.5 22.5 19.5899 22.5 16"
                fill="none"
                stroke="currentColor"
                strokeWidth="2.5"
                strokeLinecap="round"
            />
            <circle cx="16" cy="16" r="2.75" fill="currentColor" />
            <circle cx="25.5" cy="6.5" r="2.5" fill="currentColor" />
        </svg>
    );
}
