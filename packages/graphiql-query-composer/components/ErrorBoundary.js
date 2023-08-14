import useErrorBoundary from "use-error-boundary";

const ErrorBoundary = ({ children }) => {
  const { ErrorBoundary, didCatch, error } = useErrorBoundary();

  if (didCatch) {
    console.warn({
      error,
    });
  }

  return didCatch ? (
    <div style={{ padding: 18, fontFamily: "sans-serif" }}>
      <div>Something went wrong</div>
      <details style={{ whiteSpace: "pre-wrap" }}>
        {error ? error.message : null}
        <br />
        {error.stack ? error.stack : null}
      </details>
    </div>
  ) : (
    <ErrorBoundary>{children}</ErrorBoundary>
  );
};

export default ErrorBoundary;
