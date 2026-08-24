import { HeuristicTransactionLinkSuggestionService } from "./HeuristicTransactionLinkSuggestionService";
import type { TransactionLinkSuggestionService } from "./types";

export * from "./types";
export { HeuristicTransactionLinkSuggestionService } from "./HeuristicTransactionLinkSuggestionService";

let activeService: TransactionLinkSuggestionService = new HeuristicTransactionLinkSuggestionService();

export function getTransactionLinkSuggestionService(): TransactionLinkSuggestionService {
  return activeService;
}

export function setTransactionLinkSuggestionService(service: TransactionLinkSuggestionService): void {
  activeService = service;
}
