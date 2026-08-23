import React from "react";

interface TransactionDateProps {
  date: string;
  time?: string;
  align?: "left" | "right";
  size?: "sm" | "md";
}

export const TransactionDate: React.FC<TransactionDateProps> = ({
  date,
  time,
  align = "left",
  size = "md",
}) => {
  const dateClass = size === "sm" ? "text-[11px] text-slate-400" : "text-slate-200";
  const timeClass = size === "sm" ? "text-[9px] text-slate-500" : "text-[10px] text-slate-500";

  return (
    <div className={`font-mono whitespace-nowrap leading-tight ${align === "right" ? "text-right" : ""}`}>
      <div className={dateClass}>{date}</div>
      {time ? <div className={timeClass}>{time}</div> : null}
    </div>
  );
};
