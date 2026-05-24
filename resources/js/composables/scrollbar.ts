export const customScrollbar = [
    // Firefox / standards-based fallback
    "[scrollbar-width:thin]",
    "[scrollbar-color:#94a3b8_transparent]",
    "dark:[scrollbar-color:#0f172a_transparent]",

    // Basic sizing - thin but not too thin
    "[&::-webkit-scrollbar]:w-1.5",
    "[&::-webkit-scrollbar]:h-1.5",

    // The draggable thumb
    "[&::-webkit-scrollbar-thumb]:bg-gray-300",
    "[&::-webkit-scrollbar-thumb]:rounded-full",

    // Smooth interactions feel professional
    "[&::-webkit-scrollbar-thumb]:transition-colors",
    "[&::-webkit-scrollbar-thumb]:duration-200",
    "[&::-webkit-scrollbar-thumb:hover]:bg-gray-400",
    "dark:[&::-webkit-scrollbar-thumb]:bg-slate-900",
    "dark:[&::-webkit-scrollbar-thumb:hover]:bg-gray-900",

    // Clean track and hidden buttons
    "[&::-webkit-scrollbar-track]:bg-transparent",
    "[&::-webkit-scrollbar-button]:hidden",
    // "dark:[&::-webkit-scrollbar-track]:bg-neutral-700"
].join(" ");
