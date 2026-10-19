export function formatFareRuleSections(text) {
    return String(text ?? "")
        .split(/-{3,}/)
        .map((section) => section.trim())
        .filter(Boolean)
        .map((section) => {
            const heading = section.match(/^\/\/\s*([\s\S]*?)\s*\/\//);
            const body = heading ? section.slice(heading[0].length).trim() : section;
            return {
                heading: heading?.[1]?.trim() || "",
                paragraphs: body
                    .split(/\r?\n+|\s+(?=\d{1,2}[.-]\s*(?=[A-Za-z]))/)
                    .map((paragraph) => paragraph.trim())
                    .filter(Boolean),
            };
        });
}
