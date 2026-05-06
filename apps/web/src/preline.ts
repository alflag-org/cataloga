import { useEffect } from "react";
import { useLocation } from "react-router-dom";

declare global {
  interface Window {
    HSStaticMethods?: {
      autoInit: () => void;
    };
  }
}

async function loadPreline() {
  await import("preline/dist/index.js");
}

export function usePreline() {
  const location = useLocation();

  useEffect(() => {
    const init = async () => {
      await loadPreline();
      window.HSStaticMethods?.autoInit();
    };

    init();
  }, [location.pathname]);
}
