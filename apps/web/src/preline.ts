import { useEffect } from "react";
import { useLocation } from "react-router-dom";

async function loadPreline() {
  await import("preline");
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
