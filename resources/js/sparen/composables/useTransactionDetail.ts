import { ref, type Ref } from "vue";
import type { Transaction } from "../types";

const selectedTransaction = ref<Transaction | null>(null);

export function useTransactionDetail(): {
  selectedTransaction: Ref<Transaction | null>;
  openTransactionDetail: (tx: Transaction) => void;
  closeTransactionDetail: () => void;
} {
  const openTransactionDetail = (tx: Transaction) => {
    selectedTransaction.value = tx;
  };

  const closeTransactionDetail = () => {
    selectedTransaction.value = null;
  };

  return {
    selectedTransaction,
    openTransactionDetail,
    closeTransactionDetail,
  };
}
