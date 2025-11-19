export function dynamicBackgroundColor(variant?: string, withOpacity?: boolean): string {
    if (!variant)  {
        return '';
    }

    if (!withOpacity)  {
        withOpacity = false;
    }

    if (!withOpacity) {
        const colorVariants = {
            red: "bg-red-500",
            orange: "bg-orange-500",
            amber: "bg-amber-500",
            yellow: "bg-yellow-500",
            lime: "bg-lime-500",
            green: "bg-green-500",
            emerald: "bg-emerald-500",
            teal: "bg-teal-500",
            cyan: "bg-cyan-500",
            sky: "bg-sky-500",
            blue: "bg-blue-500",
            indigo: "bg-indigo-500",
            violet: "bg-violet-500",
            purple: "bg-purple-500",
            fuchsia: "bg-fuchsia-500",
            pink: "bg-pink-500",
            rose: "bg-rose-500",
            slate: "bg-slate-500",
            gray: "bg-gray-500",
            zinc: "bg-zinc-500",
            neutral: "bg-neutral-500",
            stone: "bg-stone-500",
            primary: "bg-primary-500",
        };

        return colorVariants[variant] ?? colorVariants['blue'];
    }

    if (withOpacity) {
        const colorVariants = {
            red: "bg-red-600/10",
            orange: "bg-orange-600/10",
            amber: "bg-amber-600/10",
            yellow: "bg-yellow-600/10",
            lime: "bg-lime-600/10",
            green: "bg-green-600/10",
            emerald: "bg-emerald-600/10",
            teal: "bg-teal-600/10",
            cyan: "bg-cyan-600/10",
            sky: "bg-sky-600/10",
            blue: "bg-blue-600/10",
            indigo: "bg-indigo-600/10",
            violet: "bg-violet-600/10",
            purple: "bg-purple-600/10",
            fuchsia: "bg-fuchsia-600/10",
            pink: "bg-pink-600/10",
            rose: "bg-rose-600/10",
            slate: "bg-slate-600/10",
            gray: "bg-gray-600/10",
            zinc: "bg-zinc-600/10",
            neutral: "bg-neutral-600/10",
            stone: "bg-stone-600/10",
            primary: "bg-primary-600/10",
        };

        return colorVariants[variant] ?? colorVariants['blue'];
    }
}

export function dynamicTextColor(variant?: string): string {
    if (!variant)  {
        return '';
    }

    const colorVariants = {
        red: "text-red-500",
        orange: "text-orange-500",
        amber: "text-amber-500",
        yellow: "text-yellow-500",
        lime: "text-lime-500",
        green: "text-green-500",
        emerald: "text-emerald-500",
        teal: "text-teal-500",
        cyan: "text-cyan-500",
        sky: "text-sky-500",
        blue: "text-blue-500",
        indigo: "text-indigo-500",
        violet: "text-violet-500",
        purple: "text-purple-500",
        fuchsia: "text-fuchsia-500",
        pink: "text-pink-500",
        rose: "text-rose-500",
        slate: "text-slate-500",
        gray: "text-gray-500",
        zinc: "text-zinc-500",
        neutral: "text-neutral-500",
        stone: "text-stone-500",
        primary: "text-primary-500",
    };

    return colorVariants[variant] ?? colorVariants['blue'];
}
