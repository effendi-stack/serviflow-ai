export function AiBadge({ children }: { children: React.ReactNode }) {
  return (
    <span className="badge-ai">
      <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
        <path d="M5 0L6.1 3.9L10 5L6.1 6.1L5 10L3.9 6.1L0 5L3.9 3.9L5 0Z" fill="currentColor" />
      </svg>
      {children}
    </span>
  )
}
